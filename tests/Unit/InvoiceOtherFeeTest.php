<?php

use App\Models\Invoice;
use App\Rules\ValidIndonesianMoney;

describe('Invoice::other_fee accessor', function () {
    it('returns empty array when DB stores integer 0', function () {
        $invoice = new Invoice();
        $invoice->setRawAttributes(['other_fee' => '0']);
        $result = $invoice->other_fee;
        expect($result)->toBeArray()->toBeEmpty();
    });

    it('returns empty array when DB stores null', function () {
        $invoice = new Invoice();
        $invoice->setRawAttributes(['other_fee' => null]);
        $result = $invoice->other_fee;
        expect($result)->toBeArray()->toBeEmpty();
    });

    it('returns empty array when DB stores empty string', function () {
        $invoice = new Invoice();
        $invoice->setRawAttributes(['other_fee' => '']);
        $result = $invoice->other_fee;
        expect($result)->toBeArray()->toBeEmpty();
    });

    it('returns correct array when DB stores valid JSON', function () {
        $fees = [['name' => 'Biaya Kirim', 'amount' => 5000]];
        $invoice = new Invoice();
        $invoice->setRawAttributes(['other_fee' => json_encode($fees)]);
        $result = $invoice->other_fee;
        expect($result)->toBeArray()->toHaveCount(1);
        expect($result[0]['name'])->toBe('Biaya Kirim');
        expect($result[0]['amount'])->toBe(5000);
    });

    it('encodes array as JSON when setting via mutator', function () {
        $fees = [['name' => 'Biaya Test', 'amount' => 10000]];
        $invoice = new Invoice();
        $invoice->other_fee = $fees;
        $raw = $invoice->getAttributes()['other_fee'];
        expect($raw)->toBeString();
        $decoded = json_decode($raw, true);
        expect($decoded)->toBeArray()->toHaveCount(1);
        expect($decoded[0]['name'])->toBe('Biaya Test');
    });

    it('encodes empty array when setting null via mutator', function () {
        $invoice = new Invoice();
        $invoice->other_fee = null;
        $raw = $invoice->getAttributes()['other_fee'];
        expect(json_decode($raw, true))->toBeArray()->toBeEmpty();
    });
});

describe('ValidIndonesianMoney rule', function () {
    it('passes for plain number 100000', function () {
        $rule = new ValidIndonesianMoney();
        expect($rule->passes('amount', '100000'))->toBeTrue();
    });

    it('passes for formatted Indonesian money 1.000.000', function () {
        $rule = new ValidIndonesianMoney();
        expect($rule->passes('amount', '1.000.000'))->toBeTrue();
    });

    it('passes for Rp prefixed amount', function () {
        $rule = new ValidIndonesianMoney();
        expect($rule->passes('amount', 'Rp 500.000'))->toBeTrue();
    });

    it('fails for null', function () {
        $rule = new ValidIndonesianMoney();
        expect($rule->passes('amount', null))->toBeFalse();
    });

    it('fails for empty string', function () {
        $rule = new ValidIndonesianMoney();
        expect($rule->passes('amount', ''))->toBeFalse();
    });

    it('fails for zero', function () {
        $rule = new ValidIndonesianMoney();
        expect($rule->passes('amount', '0'))->toBeFalse();
    });

    it('fails for negative value', function () {
        $rule = new ValidIndonesianMoney();
        expect($rule->passes('amount', '-100'))->toBeFalse();
    });

    it('returns correct error message', function () {
        $rule = new ValidIndonesianMoney();
        expect($rule->message())->toContain('deposit');
    });
});
