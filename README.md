# Thoughts Timeline

Thoughts Timeline is a pure PHP, HTML, CSS, and JavaScript web app backed by MySQL. It is designed for recording daily events, thoughts, physical effects, and a feeling rate, then browsing those records grouped by month and day.

The project has two access levels:

- Admin: can add, edit, delete, and view events. Admin can also read viewer comments.
- Viewer: can view events and add comments to a specific event. Viewer cannot change event data.

Phases 1 through 8 are implemented. The development plan is tracked in `TASKS.md`.

## Core Features

Implemented:

- Password-based login with separate admin and viewer passwords.
- Shared dashboard showing records grouped by month and day.
- Month and day sections are collapsed by default.
- Admin-only event creation modal.
- Clickable event rows that open a detail modal.
- Role-specific detail modal controls for admin and viewer.
- Viewer comment submission for each event.
- Admin unread comment indicators.
- Admin comment review with read tracking.
- Responsive baseline interface for mobile, tablet, and desktop.

Planned:

- Admin event editing and deleting.
- Customization for theme, colors, font size, font family, and density.

## Event Fields

Each event contains:

- Date, defaulting to the current day when creating a new event.
- Event text, up to 1024 characters.
- Thoughts text, up to 1024 characters.
- Physical Effect text, up to 1024 characters.
- Feeling Rate, a real number from `0` to `10`.

Each day can contain multiple events. The dashboard groups events like this:

```text
Month
  Day
    Event
    Event
  Day
    Event
Month
  Day
    Event
```

## Planned Technology

- PHP with no framework.
- MySQL database.
- PDO for database access.
- HTML templates rendered by PHP.
- CSS for responsive styling and themes.
- Small JavaScript layer for modals, accordions, form helpers, and UI interactions.

## Project Structure

```text
.
├── README.md
├── TASKS.md
├── public/
│   ├── index.php
│   ├── dashboard.php
│   ├── create_comment.php
│   ├── event_comments.php
│   ├── create_event.php
│   ├── logout.php
│   └── assets/
│       ├── css/
│       │   └── app.css
│       └── js/
│           └── app.js
└── thoughts-api/
    ├── .env
    ├── config.example.php
    ├── database/
    │   ├── schema.sql
    │   └── charset_migration.sql
    ├── src/
    │   ├── auth.php
    │   ├── comments.php
    │   ├── config.php
    │   ├── database.php
    │   └── events.php
    └── tests/
        └── run.php
```

For cPanel, set the domain document root to `public/`. If cPanel uses `public_html`, place the contents of `public/` there and keep `thoughts-api/` as a sibling directory outside the web root.

## Planned Database Tables

### `events`

Stores the main records.

- `id`
- `event_date`
- `event_text`
- `thoughts`
- `physical_effect`
- `feeling_rate`
- `created_at`
- `updated_at`

### `comments`

Stores viewer comments on events.

- `id`
- `event_id`
- `comment_text`
- `is_read_by_admin`
- `created_at`
- `read_at`

### `settings`

Stores future admin customization settings.

- `setting_key`
- `setting_value`

## Access Rules

Admin can:

- View all events.
- Add events.
- Edit events.
- Delete events.
- View comments.
- Mark comments as read by opening an event.
- Change visual customization settings in a later phase.

Viewer can:

- View all events.
- Open event details.
- Add comments to events.

Viewer cannot:

- Add events.
- Edit events.
- Delete events.
- Read admin-only settings pages.
- Mark comments as read.

## Security Plan

The implementation should follow these rules:

- Store password hashes in local config, not plaintext passwords.
- Keep local config out of version control.
- Use PDO prepared statements for all database queries.
- Escape rendered user content with `htmlspecialchars`.
- Validate all user input on the server.
- Use CSRF tokens for create, update, delete, comment, and settings forms.
- Check the user role on every protected action.
- Use secure session cookie settings.
- Add simple login throttling to slow repeated failed attempts.

## UI Direction

The dashboard should be calm, readable, and clear. The main screen should prioritize the grouped timeline, with controls that are obvious but not visually noisy.

Planned interface elements:

- Centered login screen.
- Dashboard header with role label and logout.
- Admin plus button for new events.
- Month accordion sections.
- Day accordion sections nested inside months.
- Event rows with feeling rate and comment indicators.
- Detail modal for viewing event data.
- Admin edit/delete controls.
- Viewer comment input.
- Responsive modal layout that works on narrow screens.
- Smooth accordion and modal transitions.

## Development Plan

See `TASKS.md` for the phase-by-phase implementation plan.

Current status: Phase 8 is complete. Phase 9, admin edit/delete, is next.

## Tests

Run the current PHP test suite from the project root:

```bash
php thoughts-api/tests/run.php
```

The current tests cover configuration loading, password role resolution, session role helpers, CSRF tokens, domain constants, event validation, comment validation, comment review payloads, timeline grouping, event detail payloads, feeling-rate formatting, and schema smoke checks.

Recommended order:

1. Build foundation and database.
2. Add login and role routing.
3. Build the grouped read-only dashboard.
4. Add admin event creation.
5. Add event detail modal.
6. Add viewer comments.
7. Add admin comment review.
8. Add admin edit/delete.
9. Polish responsive UI and animations.
10. Add customization settings.
11. Harden security and document deployment.

## Future Local Setup

The final setup will likely follow this shape:

1. Create a MySQL database.
2. Import `thoughts-api/database/schema.sql`.
3. Copy `thoughts-api/config.example.php` to the real local config file.
4. Set database credentials.
5. Generate password hashes for admin and viewer passwords.
6. Start PHP's local server from the `public` directory:

```bash
php -S localhost:8000 -t public
```

The exact instructions will be finalized during the implementation phases.
