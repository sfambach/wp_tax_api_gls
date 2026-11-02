# src/Core

The GLS notice reader (`GlsNoticeMailParser`): recognises GLS's parcel notices and reads tracking number and status (on the way / delivered) into a proposal without an order number.
Plain PHP 8.2, **no WordPress call**; it implements the modeller's contract `Taxmod\Core\Import\Mail\OrderMailParser`.
Concept: [`docs/pakete/import/module-schnitt.md`](https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md).
