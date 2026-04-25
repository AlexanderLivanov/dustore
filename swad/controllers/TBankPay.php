<?php

class TBankPay
{
    private string $terminalKey;
    private string $password;
    private string $apiUrl = 'https://securepay.tinkoff.ru/v2/';

    public function __construct(string $terminalKey, string $password)
    {
        $this->terminalKey = $terminalKey;
        $this->password = $password;
    }

    /**
     * Создание платежа
     */
    public function initPayment(array $params): array
    {
        $payload = array_merge([
            'TerminalKey' => $this->terminalKey,
        ], $params);

        $payload['Token'] = $this->makeToken($payload);

        return $this->request('Init', $payload);
    }

    /**
     * Проверка статуса платежа
     */
    public function getState(string $paymentId): array
    {
        $payload = [
            'TerminalKey' => $this->terminalKey,
            'PaymentId'   => $paymentId,
        ];

        $payload['Token'] = $this->makeToken($payload);

        return $this->request('GetState', $payload);
    }

    private function makeToken(array $params): string
    {
        $params['Password'] = $this->password;
        ksort($params);
        return hash('sha256', implode('', $params));
    }

    private function request(string $method, array $data): array
    {
        $ch = curl_init($this->apiUrl . $method);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?? [];
    }
}
