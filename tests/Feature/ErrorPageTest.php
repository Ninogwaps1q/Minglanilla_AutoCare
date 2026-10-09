<?php

test('missing pages use the shared error experience', function () {
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertSee('Page not found')
        ->assertSee('Return home');
});
