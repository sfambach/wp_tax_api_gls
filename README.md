# Taxmod GLS

GLS connection for the [Taxonomy Modeller](https://github.com/sfambach/wp-taxonomy-modeler) (WordPress plugin).

**Status 0.0.1 (needs modeller 0.1.89 and [Taxmod Mail](https://github.com/sfambach/wp_tax_mail) 0.6.0 or later):
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

GLS offers a Track & Trace API (Developer Portal, «Track And Trace V1» with «Authentication API v2»); it is planned
for a later version. Every connection is a module of its own (D-1006,
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
