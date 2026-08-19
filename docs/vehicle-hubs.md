# Vehicle Hubs

Public vehicle hubs (`revit_vehicle` CPT) provide one canonical landing page per deterministic vehicle identity (e.g. `bmw-x3-g01-m40i`).

## Identity

Vehicle keys are built from manufacturer, model, generation, and trim slugs — never from post titles.

Meta key: `_revit_vehicle_key`

## Creation

Hubs are created as **drafts only** via **RevIt Publisher → Vehicles → Create Hub Draft**. Publishing requires manual operator action.

## Dynamic content

Article sections (Common Problems, Maintenance, Modifications, etc.) are generated from RevIt article relationships and article types. Do not manually duplicate article lists in hub post content.

## URL strategy

Default stable URL:

`/vehicles/bmw-x3-g01-m40i/`

We use a flat slug under `/vehicles/` rather than nested `/vehicles/bmw/x3/g01/m40i/` to avoid rewrite complexity and duplicate route risk. Manufacturer index pages use `/vehicles/manufacturer/bmw/` when at least two published hubs exist.

## Disclaimer

Vehicle hubs improve navigation for users and crawlers; they are not guaranteed ranking mechanisms.
