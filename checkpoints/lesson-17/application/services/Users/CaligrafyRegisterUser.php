<?php

use Caligrafy\Capability;
use Caligrafy\Context;
use Caligrafy\Service;
use Caligrafy\ServiceError;

// Sign-up: the sign-up page of Route::auth (or AuthController@register) calls it, then the person signs in, or is signed
// in at once with Route::auth's signInAfterRegister. Signing in itself is the web session's, which capabilities never touch.
// Web pages and the command line only: over REST or MCP anyone could create accounts in bulk.
#[Capability(
    name: 'register_user',
    description: 'Creates an account that can sign in, with permissions 0. Used by the sign-up page.',
    scope: 'users:write',
    public: true
)]
class CaligrafyRegisterUser extends Service
{
    // Bookshelf: people sign in with their email address: the username column holds it, in lower case
    public $rules = array('username' => 'required|valid_email|max_len,254', 'passcode' => 'required|min_len,8|max_len,72');
    public $filters = array('username' => 'trim|lower_case');
    public $descriptions = array('username' => 'An email address: what the person signs in with', 'passcode' => 'The password: 8 to 72 characters');

    public function handle(array $input, Context $context)
    {
        if (!in_array($context->channel, array('web', 'cli'), true)) {
            throw ServiceError::forbidden('Sign up on the sign-up page');
        }
        if (User::where('username', $input['username'])->exists()) {
            throw ServiceError::conflict('An account already uses this email');
        }
        try {
            $user = User::find(User::insert(array('username' => $input['username'], 'passcode' => User::hashPassword($input['passcode']), 'permissions' => 0)));
        } catch (\Throwable $e) {
            if (CaligrafyUsers::isDuplicate($e)) {
                throw ServiceError::conflict('An account already uses this email');
            }
            throw $e;
        }
        return CaligrafyUsers::profile($user);
    }
}
