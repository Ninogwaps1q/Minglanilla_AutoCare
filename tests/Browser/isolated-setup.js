import { execFileSync, spawn } from 'node:child_process';
import { createConnection } from 'node:net';
import { resolve, sep } from 'node:path';
import { existsSync, unlinkSync } from 'node:fs';

async function portIsOccupied() {
    return new Promise(resolveCheck => {
        const connection = createConnection({ host: '127.0.0.1', port: 8877 });
        connection.once('connect', () => {
            connection.end();
            resolveCheck(true);
        });
        connection.once('error', () => resolveCheck(false));
    });
}

export default async function setup() {
    if (await portIsOccupied()) {
        throw new Error('Port 8877 is already in use; isolated browser tests will not run against an existing server.');
    }
    const env = { ...process.env, APP_ENV: 'testing', APP_DEBUG: 'false', CACHE_STORE: 'array', SESSION_DRIVER: 'file', SESSION_COOKIE: 'autocare_browser_test', MAIL_MAILER: 'array', QUEUE_CONNECTION: 'sync' };
    const fixture = JSON.parse(execFileSync('php', ['tests/Browser/prepare.php'], { env, encoding: 'utf8', timeout: 120000, windowsHide: true }));
    process.env.BROWSER_FIXTURE = JSON.stringify(fixture);
    const server = spawn('php', ['-S', '127.0.0.1:8877', resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], {
        cwd: resolve('public'),
        env: { ...env, DB_CONNECTION: 'sqlite', DB_DATABASE: fixture.database, DB_URL: '', APP_URL: 'http://127.0.0.1:8877' },
        windowsHide: true, stdio: 'ignore',
    });
    let ready = false;
    for (let attempt = 0; attempt < 50; attempt++) {
        if (server.exitCode !== null) break;
        try {
            const response = await fetch('http://127.0.0.1:8877/login', { signal: AbortSignal.timeout(3000) });
            if (response.ok && (await response.text()).includes('name="email"')) { ready = true; break; }
        } catch {}
        await new Promise(resolve => setTimeout(resolve, 100));
    }
    if (!ready) {
        server.kill();
        throw new Error('Isolated browser server could not start on port 8877.');
    }
    return async () => {
        if (server.exitCode === null && server.signalCode === null) {
            const exited = new Promise(resolve => server.once('exit', resolve));
            server.kill();
            await exited;
        }
        if (resolve(fixture.database).startsWith(resolve('storage/framework/testing') + sep)) {
            unlinkSync(fixture.database);
        }
        const logoPath = resolve('storage/app/public', fixture.logo);
        if (/^shop-logos\/browser-map-[a-f0-9]{16}\.png$/.test(fixture.logo)
            && logoPath.startsWith(resolve('storage/app/public/shop-logos') + sep)
            && existsSync(logoPath)) {
            unlinkSync(logoPath);
        }
        const customerPhotoPath = resolve('storage/app/public', fixture.customerPhoto);
        if (/^profile-photos\/browser-customer-[a-f0-9]{16}\.png$/.test(fixture.customerPhoto)
            && customerPhotoPath.startsWith(resolve('storage/app/public/profile-photos') + sep)
            && existsSync(customerPhotoPath)) {
            unlinkSync(customerPhotoPath);
        }
    };
}
