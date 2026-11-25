<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $attributes = $request->validate(['locale' => ['required', Rule::in(array_keys(SetLocale::LANGUAGES))]]);
        $request->user()?->forceFill(['locale' => $attributes['locale']])->save();
        Cookie::queue('autocare_locale', $attributes['locale'], 60 * 24 * 365);

        return back();
    }
}
