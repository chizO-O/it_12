<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Exception;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $request->session()->forget('google_link_user_id');

        return $this->start();
    }

    public function link(Request $request): RedirectResponse
    {
        if (! $this->configured()) {
            return redirect()->route('account')->withErrors(['google' => 'Google sign-in is not available yet.']);
        }

        $request->session()->put('google_link_user_id', $request->user()->id);

        return $this->start();
    }

    public function callback(Request $request): RedirectResponse
    {
        $linkUserId = $request->session()->pull('google_link_user_id');
        $destination = $linkUserId ? 'account' : 'login';

        if (! $this->configured()) {
            return redirect()->route($destination)->withErrors(['google' => 'Google sign-in is not available yet.']);
        }

        if ($request->has('error')) {
            $request->session()->forget('state');

            return redirect()->route($destination)->withErrors(['google' => 'Google sign-in was cancelled. You can try again.']);
        }

        try {
            // Socialite validates the OAuth state against this browser's session.
            $google = Socialite::driver('google')->user();
        } catch (Exception) {
            return redirect()->route($destination)->withErrors(['google' => 'Google sign-in could not be completed. Please try again.']);
        }

        $email = strtolower((string) $google->getEmail());
        $googleId = (string) $google->getId();

        if (($google->user['email_verified'] ?? false) !== true
            || $googleId === '' || strlen($googleId) > 255
            || Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']])->fails()) {
            return redirect()->route($destination)->withErrors(['google' => 'Please use a Google account with a verified email address.']);
        }

        if ($linkUserId) {
            abort_unless($request->user() && $request->user()->id === $linkUserId && $request->user()->hasVerifiedEmail(), 403);

            if ($email !== strtolower($request->user()->email)) {
                return redirect()->route('account')->withErrors(['google' => 'Use the Google account with the same email address as your GroveMap account.']);
            }

            if ($request->user()->google_id && $request->user()->google_id !== $googleId) {
                return redirect()->route('account')->withErrors(['google' => 'A different Google account is already connected.']);
            }

            try {
                $request->user()->forceFill(['google_id' => $googleId])->save();
            } catch (UniqueConstraintViolationException) {
                return redirect()->route('account')->withErrors(['google' => 'This Google account is already connected to another account.']);
            }

            return redirect()->route('account')->with('status', 'Google sign-in has been connected.');
        }

        // Never switch accounts in an already authenticated browser.
        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        $user = User::where('google_id', $googleId)->first();

        if (! $user) {
            // Matching an email alone is not authorization to link an existing account.
            if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
                return redirect()->route('login')->withErrors([
                    'google' => 'An account with this email already exists. Sign in with your password, then connect Google from My account.',
                ]);
            }

            $user = new User([
                'name' => mb_substr($google->getName() ?: 'Buyer', 0, 255),
                'email' => $email,
            ]);
            $user->role = UserRole::Buyer;
            $user->google_id = $googleId;
            $user->email_verified_at = now();

            try {
                $user->save();
            } catch (UniqueConstraintViolationException) {
                return redirect()->route('login')->withErrors(['google' => 'This account was just registered. Please sign in again.']);
            }

            event(new Registered($user));
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    private function start(): RedirectResponse
    {
        if (! $this->configured()) {
            return redirect()->route('login')->withErrors(['google' => 'Google sign-in is not available yet. Please sign in with email.']);
        }

        return Socialite::driver('google')->with(['prompt' => 'select_account'])->redirect();
    }

    private function configured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }
}
