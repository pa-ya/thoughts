# TASKS

This project will be built as a pure PHP, HTML, CSS, and JavaScript application backed by MySQL. The plan below separates the work into phases so each phase produces a working, testable section of the app.

Current status: Phase 5 is complete. Phase 6 is next.

## Phase 1: Project Foundation

Status: Complete.

Goal: Create the base project structure and configuration.

- Create a simple PHP project layout:
  - `public/index.php` for login.
  - `public/dashboard.php` for the shared timeline view.
  - `public/assets/css/app.css` for styles.
  - `public/assets/js/app.js` for browser behavior.
  - `thoughts-api/src/config.php` for application settings.
  - `thoughts-api/src/database.php` for PDO connection setup.
  - `thoughts-api/src/auth.php` for session and role handling.
  - `thoughts-api/src/events.php` for event data access.
  - `thoughts-api/src/comments.php` for comment data access.
  - `thoughts-api/database/schema.sql` for MySQL schema.
- Add `.gitignore` for local config and generated files.
- Add `thoughts-api/config.example.php` showing required settings without secrets.
- Use PHP sessions for login state.
- Define two roles:
  - `admin`: can create, edit, delete, view events, and read comments.
  - `viewer`: can view events and add comments only.

Acceptance criteria:

- Project has a clear directory structure.
- Config is separated from committed secrets.
- PHP can connect to MySQL through PDO.
- Sessions can store the current role.

## Phase 2: Database Schema

Status: Complete.

Goal: Design the database tables for events, comments, and future customization.

- Create `events` table:
  - `id`
  - `event_date`
  - `event_text`
  - `thoughts`
  - `physical_effect`
  - `feeling_rate`
  - `created_at`
  - `updated_at`
- Create `comments` table:
  - `id`
  - `event_id`
  - `comment_text`
  - `is_read_by_admin`
  - `created_at`
  - `read_at`
- Create `settings` table for later customization:
  - `setting_key`
  - `setting_value`
- Add indexes:
  - `events.event_date`
  - `comments.event_id`
  - `comments.is_read_by_admin`
- Enforce database constraints where practical:
  - `feeling_rate` must be between `0` and `10`.
  - Event text fields should support up to 1024 characters.
  - Deleting an event should delete its comments.

Acceptance criteria:

- Fresh database can be created from `thoughts-api/database/schema.sql`.
- Event and comment relationships are enforced.
- Queries can efficiently group events by month and day.

## Phase 3: Login And Role Routing

Status: Complete.

Goal: Build the first page with admin/viewer password access.

- Create a centered login screen.
- Add one password input and submit button.
- Compare submitted password against configured admin and viewer password hashes.
- Redirect authenticated users to `dashboard.php`.
- Store role in session.
- Add logout action.
- Show a clear error for invalid password.
- Never store plaintext passwords in the database or source files.

Acceptance criteria:

- Admin password logs in as admin.
- Viewer password logs in as viewer.
- Invalid password does not reveal which role failed.
- Logged out users cannot access the dashboard.

## Phase 4: Timeline Read View

Status: Complete.

Goal: Display all events grouped by month, then day, with all sections collapsed by default.

- Query events ordered by `event_date DESC`, then `created_at DESC`.
- Render month groups such as `June 2026`.
- Inside each month, render day groups such as `Friday, June 12, 2026`.
- Inside each day, render event summary rows.
- Month and day groups should be expandable/collapsible.
- All groups should start collapsed.
- Each event row should clearly show:
  - Event
  - Feeling rate
  - Comment status indicator when comments exist
  - New comment indicator when unread comments exist
- Use semantic HTML buttons for expand/collapse controls.
- Add empty-state UI when there are no events yet.

Acceptance criteria:

- Admin and viewer can both see the same grouped event list.
- Months can be expanded to show days.
- Days can be expanded to show events.
- Event grouping remains correct across multiple months and multiple days.

## Phase 5: Admin Event Creation

Status: Complete.

Goal: Add the admin-only plus button and creation modal.

- Show a floating or toolbar plus button only to admin users.
- Open a modal with these fields:
  - Date, defaulting to today.
  - Event, text, max 1024 characters.
  - Thoughts, text, max 1024 characters.
  - Physical Effect, text, max 1024 characters.
  - Feeling Rate, real number between `0` and `10`.
- Validate all fields on the client for usability.
- Validate all fields again on the server for security.
- Save valid submissions to MySQL.
- Return to dashboard with the new event visible in the correct date group.

Acceptance criteria:

- Admin can add events.
- Viewer cannot see or access creation controls.
- Invalid inputs are rejected.
- New events appear under the proper month and day.

## Phase 6: Event Detail Modal

Goal: Let users click an event and see its full details.

- Clicking an event opens a modal.
- Admin sees:
  - Event
  - Thoughts
  - Physical Effect
  - Feeling Rate
  - Date
  - Comments
  - Edit and delete controls
- Viewer sees:
  - Event
  - Thoughts
  - Physical Effect
  - Feeling Rate
  - Date
  - Comment input
- Use the same detail modal where possible, with role-based controls.
- Ensure modal is usable by keyboard and on mobile screens.

Acceptance criteria:

- Admin and viewer can inspect event details.
- Controls shown in the modal match the current role.
- Long text remains readable and does not break layout.

## Phase 7: Viewer Comments

Goal: Let viewer users add comments to a specific event.

- Add a comment form in the event detail modal for viewer users.
- Validate comment text server-side.
- Store each comment with `is_read_by_admin = 0`.
- After submission, keep the viewer on the dashboard and confirm the comment was added.
- Update event indicators:
  - Comments exist.
  - New unread comments exist.

Acceptance criteria:

- Viewer can add comments to events.
- Viewer cannot edit or delete events.
- Viewer cannot mark comments as read.
- Admin dashboard shows unread comment indicators.

## Phase 8: Admin Comment Review

Goal: Let admin view comments and automatically mark them as read.

- Show comments inside the admin event detail modal.
- Highlight unread comments before they are marked read.
- When admin opens an event with unread comments, mark those comments as read.
- Remove unread indicators after admin has viewed the comments.
- Keep a general comment indicator when read comments still exist.

Acceptance criteria:

- Admin can see all comments for an event.
- Opening an event marks unread comments as read.
- New viewer comments later create a new unread indicator.

## Phase 9: Admin Edit And Delete

Goal: Allow admin to maintain existing events.

- Add edit controls in the event detail modal.
- Reuse the creation form fields for editing:
  - Date
  - Event
  - Thoughts
  - Physical Effect
  - Feeling Rate
- Validate edited data on client and server.
- If the date changes, move the event to the correct month/day group.
- Add delete action with confirmation.
- Delete related comments through database cascade.

Acceptance criteria:

- Admin can edit event fields.
- Admin can move events between dates.
- Admin can delete events.
- Viewer has no access to edit/delete routes.

## Phase 10: Responsive UI And Visual Design

Goal: Make the app clear, beautiful, and comfortable on phones, tablets, and desktops.

- Build a polished visual system:
  - Clean dashboard layout.
  - Strong spacing and readable typography.
  - Clear role-specific controls.
  - High-contrast form fields and buttons.
  - Calm event cards with clear hierarchy.
- Make all screens responsive:
  - Login screen.
  - Dashboard header.
  - Month/day accordion sections.
  - Event rows.
  - Modals and forms.
- Add smooth but restrained animations:
  - Modal open/close.
  - Accordion expand/collapse.
  - Button hover/focus states.
  - Comment indicator transitions.
- Add accessible focus states and ARIA attributes where appropriate.

Acceptance criteria:

- Inputs are fully usable on small mobile screens.
- Event details remain readable with 1024-character fields.
- UI elements do not overlap at common viewport sizes.
- Keyboard navigation works for login, modals, and accordions.

## Phase 11: Customization Settings

Goal: Add admin-controlled visual customization.

- Add settings panel for admin.
- Support customization options:
  - Theme mode: light, dark, or system.
  - Accent color.
  - Base font size.
  - Font family choice from safe built-in options.
  - Compact or comfortable density.
- Store settings in the `settings` table.
- Apply settings through CSS custom properties.
- Viewer sees the configured appearance but cannot change it.

Acceptance criteria:

- Admin can change visual settings.
- Settings persist after reload.
- Viewer receives the configured theme.
- UI remains readable under all provided customization options.

## Phase 12: Security Hardening

Goal: Review and strengthen security before considering the project complete.

- Use password hashes, not plaintext passwords.
- Use prepared statements for all database access.
- Escape all rendered user content with `htmlspecialchars`.
- Add CSRF tokens to all forms that mutate data.
- Check role authorization on every server-side action.
- Use safe session settings:
  - `HttpOnly`
  - `SameSite=Lax`
  - Secure cookies when HTTPS is enabled.
- Limit login attempts with simple session-based throttling.
- Validate field lengths and numeric ranges server-side.

Acceptance criteria:

- No mutation route works without the right role.
- No mutation route works without a valid CSRF token.
- User-provided text renders safely.
- Passwords are never exposed in committed files.

## Phase 13: Testing And Manual QA

Goal: Verify the complete app works across roles and devices.

- Test database setup from a clean MySQL database.
- Test admin login, viewer login, invalid login, and logout.
- Test event creation, editing, deleting, and date regrouping.
- Test multiple events on the same day.
- Test multiple days in the same month.
- Test multiple months.
- Test viewer comments.
- Test admin unread comment indicators and read marking.
- Test responsive layouts at:
  - 360px mobile width.
  - 768px tablet width.
  - 1280px desktop width.
- Test long text near the 1024-character limit.

Acceptance criteria:

- All core workflows pass manually.
- No PHP warnings appear during normal use.
- Layout remains usable on tested viewport sizes.

## Phase 14: Deployment Documentation

Goal: Document how to install, configure, and run the project.

- Expand `README.md` with:
  - Requirements.
  - Database setup.
  - Configuration setup.
  - Local run instructions.
  - Password hash generation.
  - File permissions.
  - Common troubleshooting.
- Add notes for production:
  - HTTPS.
  - Secure session cookies.
  - Database backups.
  - Keeping config outside public web root.

Acceptance criteria:

- A new developer or server admin can install the app from the README.
- Required secrets are documented without being committed.
- Production notes are clear enough for a basic shared-hosting deployment.

