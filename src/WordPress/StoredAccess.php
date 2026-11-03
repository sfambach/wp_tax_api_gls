<?php declare(strict_types=1);

namespace TaxApiGls\WordPress;

use Taxmod\Core\Exception\SecretDoesNotOpen;
use Taxmod\WordPress\Import\Vault;
use TaxApiGls\Core\Api\Environment;

/**
 * Client key and secret of the «GLS» tab, the secret sealed in the modeller's vault (D-951), and the gateway they
 * belong to. Constants in wp-config.php go first; without the vault there is no form, only the constants.
 */
final class StoredAccess
{
    public const OPTION          = 'taxmod_gls_access';
    public const VAULT_CONTEXT   = 'gls';
    public const KEY_CONSTANT    = 'TAXGLS_CLIENT_KEY';
    public const SECRET_CONSTANT = 'TAXGLS_CLIENT_SECRET';
    public const ENV_CONSTANT    = 'TAXGLS_ENVIRONMENT';

    public static function vaultAvailable(): bool
    {
        return class_exists(Vault::class) && Vault::API >= 1;
    }

    public function fromConstants(): bool
    {
        return self::constant(self::KEY_CONSTANT) !== '' && self::constant(self::SECRET_CONSTANT) !== '';
    }

    public function clientKey(): string
    {
        return $this->fromConstants() ? self::constant(self::KEY_CONSTANT) : self::option()['key'];
    }

    public function clientSecret(): ?string
    {
        if ($this->fromConstants()) {
            return self::constant(self::SECRET_CONSTANT);
        }

        $sealed = self::option()['secret'];

        return $sealed === '' || ! self::vaultAvailable() ? null : $this->open($sealed);
    }

    public function complete(): bool
    {
        return $this->clientKey() !== '' && $this->clientSecret() !== null;
    }

    public function environment(): Environment
    {
        $chosen = $this->fromConstants() ? self::constant(self::ENV_CONSTANT) : self::option()['environment'];

        return Environment::tryFrom($chosen) ?? Environment::Production;
    }

    public function hasStoredSecret(): bool
    {
        return self::option()['secret'] !== '';
    }

    /** An empty secret keeps the stored one. Caller has checked capability and nonce and sanitized. */
    public function save(Environment $environment, string $clientKey, #[\SensitiveParameter] string $clientSecret): void
    {
        if (! self::vaultAvailable()) {
            return;
        }

        $stored = self::option();
        update_option(self::OPTION, [
            'environment' => $environment->value,
            'key'         => $clientKey,
            'secret'      => $clientSecret === '' ? $stored['secret'] : Vault::for(self::VAULT_CONTEXT)->seal($clientSecret),
        ], false);
    }

    /** Deletes the stored access; constants in wp-config.php stay, they are not this page's to delete. */
    public function forget(): void
    {
        delete_option(self::OPTION);
    }

    /** true when a secret is stored but no longer opens — the salts in wp-config.php changed */
    public function secretBroken(): bool
    {
        $sealed = self::option()['secret'];

        return $sealed !== '' && self::vaultAvailable() && $this->open($sealed) === null;
    }

    private function open(string $sealed): ?string
    {
        try {
            $secret = Vault::for(self::VAULT_CONTEXT)->open($sealed);

            return $secret === '' ? null : $secret;
        } catch (SecretDoesNotOpen) {
            return null;
        }
    }

    /** @return array{environment: string, key: string, secret: string} */
    private static function option(): array
    {
        $value = get_option(self::OPTION);
        $value = is_array($value) ? $value : [];
        $text = static fn (string $name): string => is_string($value[$name] ?? null) ? $value[$name] : '';

        return ['environment' => $text('environment'), 'key' => $text('key'), 'secret' => $text('secret')];
    }

    private static function constant(string $name): string
    {
        return defined($name) && is_string(constant($name)) ? trim(constant($name)) : '';
    }
}
