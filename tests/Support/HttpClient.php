<?php
declare(strict_types=1);

namespace Tests\Support;

/** A minimal browser: keeps cookies between requests and never follows redirects, so tests can inspect them. */
final class HttpClient
{
    private string $jar;

    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'crmjar');
    }

    public function __destruct()
    {
        @unlink($this->jar);
    }

    /** @return array{status:int,body:string,headers:array<string,string>,location:?string} */
    public function get(string $path): array
    {
        return $this->send($path, null);
    }

    /** @return array{status:int,body:string,headers:array<string,string>,location:?string} */
    public function post(string $path, array $data): array
    {
        return $this->send($path, $data);
    }

    /** Posts a form together with a valid CSRF token taken from $formPage. */
    public function postForm(string $path, array $data, ?string $formPage = null): array
    {
        $token = $this->token($formPage ?? '/login');

        return $this->post($path, $data + ['_token' => $token]);
    }

    public function token(string $page): string
    {
        $r = $this->get($page);
        if (preg_match('/name="_token" value="([^"]+)"/', $r['body'], $m) !== 1) {
            throw new \RuntimeException("No CSRF token on $page (status {$r['status']})");
        }

        return $m[1];
    }

    public function login(string $email, string $password): array
    {
        return $this->postForm('/login', ['email' => $email, 'password' => $password], '/login');
    }

    public function jarFile(): string
    {
        return $this->jar;
    }

    public function sessionCookie(): ?string
    {
        foreach (file($this->jar, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = ltrim($line, '#HttpOnly_');
            $parts = explode("\t", $line);
            if (count($parts) >= 7 && $parts[5] === 'clinic_crm_session') {
                return $parts[6];
            }
        }

        return null;
    }

    private function send(string $path, ?array $data): array
    {
        $ch = curl_init($this->base . $path);
        $headers = [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 30,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_HEADERFUNCTION => static function ($c, string $line) use (&$headers): int {
                if (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $headers[strtolower(trim($k))] = trim($v);
                }

                return strlen($line);
            },
        ]);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }
        $body = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        return ['status' => $status, 'body' => $body, 'headers' => $headers, 'location' => $headers['location'] ?? null];
    }
}
