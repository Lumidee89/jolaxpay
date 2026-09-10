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
