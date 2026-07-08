<?php

namespace App\Tools;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Wrapper de consultas de API para centralizar chamadas HTTP da aplicação.
 * Utiliza o facade nativo Http do Laravel por baixo dos panos.
 */
class APICall
{
    private PendingRequest $request;
    
    // Rastreio para captureData()
    private array $requestHeaders = [];
    private array $requestCookies = [];
    private string $method = 'GET';
    private string $url = '';
    private ?string $proxy = null;
    private array $data = [];
    private array $queryParams = [];

    // Resultados
    private ?Response $response = null;
    private ?Throwable $error = null;

    public function __construct()
    {
        $this->request = Http::timeout(30);
    }

    public static function make(string $url = '', string $method = 'GET'): self
    {
        return (new self())->url($url)->method($method);
    }

    /**
     * Integração com App\Tools\Ip:
     * Captura o IP informado ou descobre o IP da requisição atual do usuário 
     * e já encaminha para a ferramenta de IP buscar todos os detalhes.
     */
    public static function getIp(?string $ip = null): ?array
    {
        $ip = $ip ?: request()->ip();

        return Ip::getDetails($ip);
    }

    public function method(string $method): self
    {
        $this->method = strtoupper($method);
        
        return $this;
    }

    public function url(string $url): self
    {
        $this->url = $url;
        
        return $this;
    }

    public function withHeaders(array $headers): self
    {
        $this->requestHeaders = array_merge($this->requestHeaders, $headers);
        $this->request->withHeaders($headers);
        
        return $this;
    }

    public function withUserAgent(string $userAgent): self
    {
        $this->requestHeaders['User-Agent'] = $userAgent;
        $this->request->withUserAgent($userAgent);
        
        return $this;
    }

    public function withCookies(array $cookies, string $domain): self
    {
        $this->requestCookies = array_merge($this->requestCookies, $cookies);
        $this->request->withCookies($cookies, $domain);
        
        return $this;
    }

    /**
     * Define um Proxy nativamente repassando a instrução para o motor Guzzle do Laravel.
     * Aceita string ('http://proxy.com:80') ou array para proxies divididos por protocolo.
     */
    public function withProxy(string|array $proxy): self
    {
        $this->proxy = is_array($proxy) ? json_encode($proxy) : $proxy;
        $this->request->withOptions(['proxy' => $proxy]);
        
        return $this;
    }

    public function withoutVerifying(): self
    {
        $this->request->withoutVerifying();
        
        return $this;
    }

    public function withToken(string $token, string $type = 'Bearer'): self
    {
        $this->requestHeaders['Authorization'] = trim("{$type} {$token}");
        $this->request->withToken($token, $type);
        
        return $this;
    }
    
    public function withBasicAuth(string $username, string $password): self
    {
        $this->requestHeaders['Authorization'] = 'Basic ' . base64_encode("{$username}:{$password}");
        $this->request->withBasicAuth($username, $password);
        
        return $this;
    }

    public function asJson(): self
    {
        $this->requestHeaders['Content-Type'] = 'application/json';
        $this->request->asJson();
        
        return $this;
    }

    public function asForm(): self
    {
        $this->requestHeaders['Content-Type'] = 'application/x-www-form-urlencoded';
        $this->request->asForm();
        
        return $this;
    }

    public function acceptJson(): self
    {
        $this->requestHeaders['Accept'] = 'application/json';
        $this->request->acceptJson();
        
        return $this;
    }

    public function timeout(int $seconds): self
    {
        $this->request->timeout($seconds);
        
        return $this;
    }

    public function withData(array $data): self
    {
        $this->data = $data;
        
        return $this;
    }

    public function withQuery(array $queryParams): self
    {
        $this->queryParams = $queryParams;
        
        return $this;
    }

    public function execute(): self
    {
        try {
            if (filled($this->queryParams)) {
                $this->request->withQueryParameters($this->queryParams);
            }

            $this->response = match ($this->method) {
                'POST' => $this->request->post($this->url, $this->data),
                'PUT' => $this->request->put($this->url, $this->data),
                'PATCH' => $this->request->patch($this->url, $this->data),
                'DELETE' => $this->request->delete($this->url, $this->data),
                default => $this->request->get($this->url, $this->data),
            };
            
        } catch (Throwable $e) {
            $this->error = $e;
        }

        return $this;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function isSuccessful(): bool
    {
        return $this->response?->successful() ?? false;
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        return $this->response?->json($key, $default);
    }

    public function body(): ?string
    {
        return $this->response?->body();
    }

    public function status(): ?int
    {
        return $this->response?->status();
    }

    public function getResponseHeaders(): array
    {
        return $this->response?->headers() ?? [];
    }

    public function getResponseCookies(): array
    {
        if (! $this->response) {
            return [];
        }

        $cookieJar = $this->response->cookies();
        return $cookieJar ? $cookieJar->toArray() : [];
    }

    /**
     * Retorna o corpo da resposta como uma string bruta (Buffer),
     * ideal para processar imagens com imagecreatefromstring() ou salvar com file_put_contents().
     */
    public function buffer(): ?string
    {
        // Aumenta a memória dinamicamente para comportar buffers grandes
        ini_set('memory_limit', '512M');
        
        return $this->body();
    }

    /**
     * Retorna o Stream Resource nativo do PHP da requisição.
     * Útil para manipular arquivos muito pesados ou buffers grandes na memória nativa do PHP.
     *
     * @return resource|null
     */
    public function stream()
    {
        // Aumenta a memória dinamicamente para comportar processamento de streams pesados
        ini_set('memory_limit', '512M');
        
        return $this->response?->toPsrResponse()->getBody()->detach();
    }

    public function getError(): ?Throwable
    {
        return $this->error;
    }

    /**
     * Captura todos os dados para debug ou log no banco (Request, Response e Error)
     */
    public function captureData(): array
    {
        return [
            'request' => [
                'url' => $this->url,
                'method' => $this->method,
                'proxy' => $this->proxy,
                'headers' => $this->requestHeaders,
                'cookies' => $this->requestCookies,
                'query' => $this->queryParams,
                'payload' => $this->data,
            ],
            'response' => [
                'status' => $this->status(),
                'headers' => $this->getResponseHeaders(),
                'cookies' => $this->getResponseCookies(),
                // Evita quebrar o log com binários pesados (como imagens baixadas)
                'body' => $this->json() ?? (mb_check_encoding((string) $this->body(), 'UTF-8') ? $this->body() : '[Binary Buffer]'),
            ],
            'error' => $this->error ? [
                'message' => $this->error->getMessage(),
                'file' => $this->error->getFile(),
                'line' => $this->error->getLine(),
            ] : null,
        ];
    }
}
