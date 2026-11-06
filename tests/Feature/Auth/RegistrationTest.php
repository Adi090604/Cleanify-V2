<?php

test('registration screen can be rendered', function () {
    $response = $this->get('/register');

    $response->assertStatus(200)
        ->assertSee('Mobile Number')
        ->assertDontSee('Mobile Number <span', false)
        ->assertSee('Enter your mobile number')
        ->assertSee('Accepted formats: 09XXXXXXXXX or +639XXXXXXXXX');
});

test('new users can register with a local mobile number that is normalized', function () {
    $response = $this->post('/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'phone' => ' 09171234567 ',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com',
        'phone' => '+639171234567',
    ]);
    $response->assertRedirect(route('dashboard', absolute: false));

    $this->get(route('settings'))
        ->assertOk()
        ->assertSee('+639171234567');
});

test('web registration preserves an international Philippine mobile number', function () {
    $this->post('/register', [
        'name' => 'Test User',
        'email' => 'international@example.com',
        'phone' => '+639181234567',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertDatabaseHas('users', [
        'email' => 'international@example.com',
        'phone' => '+639181234567',
    ]);
});

test('web registration requires a valid Philippine mobile number', function (?string $phone) {
    $response = $this->from('/register')->post('/register', [
        'name' => 'Test User',
        'email' => 'invalid-phone@example.com',
        'phone' => $phone,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect('/register')->assertSessionHasErrors('phone');
    $this->assertGuest();
})->with([
    'missing' => null,
    'without Philippine prefix' => '9171234567',
    'country code without plus' => '639171234567',
    'too short' => '0917123456',
    'too long' => '091712345678',
    'invalid characters' => '0917-123-4567',
    'invalid mobile prefix' => '08171234567',
]);
