<?php

use GomdimApps\Tools\Pix;

it('builds a payload for a phone key, normalizing to +55 E.164', function () {
    $payload = Pix::make(
        keyType: 'phone',
        pixKey: '11987654321',
        merchantName: 'Empresa Teste Ltda',
        merchantCity: 'Sao Paulo',
    )
        ->amount(123.45)
        ->txId('ENC-9641')
        ->build();

    expect($payload)->toBe(
        '00020101021126360014br.gov.bcb.pix0114+55119876543215204000053039865406123.455802BR5918EMPRESA TESTE LTDA6009SAO PAULO62110507ENC964163045D7B'
    );
});

it('builds a payload for a document key, stripping formatting characters', function () {
    $payload = Pix::make(
        keyType: 'document',
        pixKey: '11.222.333/0001-81',
        merchantName: 'ACME LTDA',
        merchantCity: 'Rio de Janeiro',
    )
        ->amount(10.0)
        ->txId('ENC-2')
        ->build();

    expect($payload)->toBe(
        '00020101021126360014br.gov.bcb.pix011411222333000181520400005303986540510.005802BR5909ACME LTDA6014RIO DE JANEIRO62080504ENC26304425E'
    );
});

it('builds a payload for an email key with an optional description, sanitizing accents', function () {
    $payload = Pix::make(
        keyType: 'email',
        pixKey: 'contato@empresa.com.br',
        merchantName: 'Empresa Ação Ltda',
        merchantCity: 'São Gonçalo',
    )
        ->amount(9.99)
        ->txId('ENC-3')
        ->description('Pagamento de encomenda')
        ->build();

    expect($payload)->toBe(
        '00020101021126700014br.gov.bcb.pix0122contato@empresa.com.br0222PAGAMENTO DE ENCOMENDA52040000530398654049.995802BR5917EMPRESA ACAO LTDA6011SAO GONCALO62080504ENC363046EA8'
    );
});

it('omits the amount field and defaults the txid to *** when not given', function () {
    $payload = Pix::make(
        keyType: 'random',
        pixKey: '123e4567-e89b-42d3-a456-426614174000',
        merchantName: 'Transportadora X',
        merchantCity: 'BH',
    )->build();

    expect($payload)->toBe(
        '00020101021126580014br.gov.bcb.pix0136123e4567-e89b-42d3-a456-4266141740005204000053039865802BR5916TRANSPORTADORA X6002BH62070503***63042F79'
    );
});
