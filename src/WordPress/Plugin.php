<?php declare(strict_types=1);

namespace TaxApiGls\WordPress;

use TaxApiGls\Core\Api\ConnectionTest;
use TaxApiGls\Core\Api\Environment;
use TaxApiGls\Core\Api\GlsApiException;
use TaxApiGls\Core\Api\TokenClient;
use TaxApiGls\Core\GlsNoticeMailParser;
use Taxmod\Core\Import\Mail\OrderMailParser;

/**
 * The boundary: registers the GLS notice reader with Taxmod Mail (filter `taxmail_parsers`) and the «GLS» tab on the
 * modeller's Configuration page with access and connection test (D-1006, D-1007, D-981). It decides nothing.
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md
 */
final class Plugin
{
    public const VERSION    = '0.0.2';
    public const CAPABILITY = 'manage_options';
    public const TAB        = 'gls';
    public const LAST_CHECK = 'taxmod_gls_last_check';

    public static function boot(): void
    {
        add_action('plugins_loaded', static function (): void {
            load_plugin_textdomain('taxapigls', false, dirname(plugin_basename(dirname(__DIR__, 2) . '/wp-tax-api-gls.php')) . '/languages');

            // The contract lives in the modeller; without it there is nothing to register with.
            if (! interface_exists(OrderMailParser::class)) {
                add_action('admin_notices', static fn () => print '<div class="notice notice-warning"><p><strong>Taxmod GLS:</strong> '
                    . esc_html__('needs the Taxonomy Modeller 0.1.89 or later and Taxmod Mail 0.6.0 or later.', 'taxapigls') . '</p></div>');

                return;
            }

            $reader = new GlsNoticeMailParser(self::VERSION);
            add_filter('taxmail_parsers', static fn (array $parsers): array => [...$parsers, $reader]);
            add_filter('taxmod_config_tabs', static fn (array $tabs): array => $tabs + [self::TAB => [
                'title'       => 'GLS',
                'version'     => self::VERSION,
                'reader'      => $reader,
                'credentials' => static fn (): string => (new SettingsScreen(new StoredAccess()))->credentials(),
                'test'        => static fn (): string => self::testConnection(),
                'forget'      => static fn () => (new StoredAccess())->forget(),
                'guide'       => static fn (): string => SettingsScreen::guide(),
                'render'      => static fn (): string => '',
                'save'        => static fn (array $post): bool => self::saveAccess($post),
            ]], 30);
        });
    }

    /**
     * Validates and sanitizes the fields; the modeller has checked capability and nonce (D-945).
     *
     * @param array<string, mixed> $post already unslashed
     */
    private static function saveAccess(array $post): bool
    {
        $access = new StoredAccess();

        if ($access->fromConstants() || ! StoredAccess::vaultAvailable()) {
            return true;
        }

        $field = static fn (string $name): string => is_string($post[$name] ?? null) ? trim(sanitize_text_field($post[$name])) : '';
        $access->save(Environment::tryFrom($field('taxapigls_environment')) ?? Environment::Production, $field('taxapigls_client_key'), $field('taxapigls_client_secret'));

        return $access->complete();
    }

    /** «Test connection» of the modeller (D-981), run after saving. Returns one sentence. */
    private static function testConnection(): string
    {
        $access = new StoredAccess();
        $secret = $access->clientSecret();

        if ($access->clientKey() === '' || $secret === null) {
            return __('Client key or secret is missing — enter and save both first, or define them in wp-config.php.', 'taxapigls');
        }

        $environment = $access->environment();

        try {
            $answer = (new ConnectionTest(new TokenClient(new WpHttpTransport(), $access->clientKey(), $secret, $environment)))->run();
            update_option(self::LAST_CHECK, ['ok' => true, 'at' => current_time('mysql'), 'environment' => $environment->value, 'message' => $answer], false);

            /* translators: 1: sandbox or production, 2: what GLS answered */
            return sprintf(__('Connection works (%1$s): %2$s.', 'taxapigls'), $environment->value, $answer);
        } catch (GlsApiException $failure) {
            update_option(self::LAST_CHECK, ['ok' => false, 'at' => current_time('mysql'), 'environment' => $environment->value, 'message' => $failure->getMessage()], false);

            /* translators: 1: sandbox or production, 2: the error */
            return sprintf(__('Connection failed (%1$s): %2$s', 'taxapigls'), $environment->value, $failure->getMessage());
        }
    }
}
