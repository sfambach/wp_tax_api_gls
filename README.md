# Taxmod GLS

GLS connection for the [Taxonomy Modeller](https://github.com/sfambach/wp-taxonomy-modeler) (WordPress plugin).

**Status 0.0.3 (needs modeller 0.1.89 and [Taxmod Mail](https://github.com/sfambach/wp_tax_mail) 0.6.0 or later):
reads GLS parcel notices from the mailbox of Taxmod Mail and hands them to the modeller's inbox.** A notice carries
no shop order number; the modeller gives it to the order with the same parcel number, from whatever shop (D-1017,
D-1020), and never creates an order from it.

| Mail (subject) | Status |
|---|---|
| «Ihr Paket von <sender>» (sender will hand it over soon) | shipped |
| «Ihr Paket von <sender> ist unterwegs» | shipped |
| «Ihr Paket von <sender> wurde durch GLS zugestellt» | delivered |
| «Ihr Änderungsauftrag für die Zustellung …» (drop-off permit) | nothing |

The parcel number is read from the tracking link `gls-group.eu/track/<number>`. The shipper's «Referenz» is not used
yet.

**API (0.0.2):** the tab *GLS* stores client key and secret of an app in the GLS developer portal (secret sealed in
the modeller's vault, D-951; or `TAXGLS_CLIENT_KEY` / `TAXGLS_CLIENT_SECRET` / `TAXGLS_ENVIRONMENT` in wp-config.php).
«Test connection» asks the Authentication API v2 for a token and reads GLS's test parcel `QAS_0000008` over
«Track And Trace V1».

**Tracking (0.0.3):** other modules ask `apply_filters('taxmod_gls_tracking', null, $parcelNumber, 'stored'|'refresh')`
and get the same array as from the DHL module's `taxmod_dhl_tracking` (stage announced|shipped|delivered|problem,
status, events). `stored` never calls GLS; `refresh` does, at most 400 calls a day (quota 500). A parcel in a
ParcelShop counts as shipped, not delivered. Specification: Track And Trace V1 1.0.7 from the GLS developer portal. Every connection is a module of its own (D-1006,
[concept](https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md)).

## Setup

1. Install and set up Taxmod Mail (mailbox access).
2. Activate this module. *Taxonomy Modeller → Configuration → GLS* shows under «Mail» which mails it reads.

## Development

`composer install`, then `vendor/bin/phpunit` (core suite, no WordPress). The sample mails in `tests/Core/fixtures`
are synthetic.

## License

Copyright (C) 2026 Stefan Fambach (SteFam), https://www.fambach.net — [PolyForm Noncommercial 1.0.0](LICENSE.md).
Commercial use needs the owner's prior consent.

## Authors

- **Stefan Fambach** (SteFam) – idea and concept, review and testing – https://www.fambach.net
- **Claude** (Anthropic AI) – implementation, via Claude Code

## AI disclosure

This project is built with AI assistance. Stefan Fambach had the idea, wrote the concept and reviews and tests every change; the implementation (code and documentation) is written largely by Claude (Anthropic) via Claude Code.
