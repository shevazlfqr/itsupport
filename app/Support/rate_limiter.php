<?php
declare(strict_types=1);

class RateLimiter
{
    private string $storePath;
    private int $maxAttempts;
    private int $decayMinutes;

    public function __construct(int $maxAttempts = 5, int $decayMinutes = 15)
    {
        $this->storePath = BASE_PATH . "/storage/rate_limits";
        $this->maxAttempts = $maxAttempts;
        $this->decayMinutes = $decayMinutes;

        if (!is_dir($this->storePath)) {
            mkdir($this->storePath, 0700, true);
        }
    }

    public function tooManyAttempts(string $key): bool
    {
        $attempts = $this->getAttempts($key);
        return $attempts >= $this->maxAttempts;
    }

    public function hit(string $key): int
    {
        $file = $this->getFilePath($key);
        $attempts = $this->getAttempts($key);
        $data = [
            'attempts' => $attempts + 1,
            'expires_at' => time() + ($this->decayMinutes * 60),
        ];

        file_put_contents($file, json_encode($data), LOCK_EX);
        return $data['attempts'];
    }

    public function remainingAttempts(string $key): int
    {
        $attempts = $this->getAttempts($key);
        return max(0, $this->maxAttempts - $attempts);
    }

    public function availableIn(string $key): int
    {
        $file = $this->getFilePath($key);
        if (!file_exists($file)) {
            return 0;
        }

        $data = json_decode(file_get_contents($file), true);
        $expiresAt = $data['expires_at'] ?? 0;
        $availableIn = $expiresAt - time();

        return max(0, $availableIn);
    }

    public function clear(string $key): void
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    private function getAttempts(string $key): int
    {
        $file = $this->getFilePath($key);
        if (!file_exists($file)) {
            return 0;
        }

        $data = json_decode(file_get_contents($file), true);
        if (!$data) {
            return 0;
        }

        $expiresAt = $data['expires_at'] ?? 0;
        if (time() >= $expiresAt) {
            @unlink($file);
            return 0;
        }

        return $data['attempts'] ?? 0;
    }

    private function getFilePath(string $key): string
    {
        $hash = hash('sha256', $key);
        return $this->storePath . '/' . $hash . '.json';
    }

    public function cleanup(): void
    {
        $files = glob($this->storePath . '/*.json');
        if (!$files) {
            return;
        }

        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && time() >= ($data['expires_at'] ?? 0)) {
                @unlink($file);
            }
        }
    }
}

function rateLimiter(): RateLimiter
{
    static $instance = null;
    if ($instance === null) {
        $instance = new RateLimiter();
    }
    return $instance;
}
