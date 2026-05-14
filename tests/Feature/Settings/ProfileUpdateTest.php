<?php

beforeEach(function (): void {
    // Profile management is handled by Filament's EditProfile page.
    // See AuthAndRolesTest for profile update coverage.
    $this->markTestSkipped('Profile management is handled by Filament — see AuthAndRolesTest.');
});

test('profile page is displayed', function () {
    //
});

test('profile information can be updated', function () {
    //
});

test('email verification status is unchanged when email address is unchanged', function () {
    //
});

test('user can delete their account', function () {
    //
});

test('correct password must be provided to delete account', function () {
    //
});
