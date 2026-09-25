<?php

it('serves the public JolaxPay website from the root URL', function () {
    $this->get('/')->assertOk()
        ->assertSee('Everyday payments, made simpler')
        ->assertSee('Electricity vending')
        ->assertSee('JolaxPay Wallet')
        ->assertSee('Download JolaxPay');
});

it('keeps the staff login available under the admin prefix', function () {
    $this->get('/admin/login')->assertOk();
});

it('serves the public privacy policy and links to it from the website', function () {
    $this->get('/privacy-policy')->assertOk()
        ->assertSee('JolaxPay Privacy Policy')
        ->assertSee('Nigeria Data Protection Act 2023')
        ->assertSee('support@jolaxpay.com');

    $this->get('/')->assertOk()
        ->assertSee(route('privacy-policy'), false);
});
