# Decks

Decks is a web app for creating and presenting lectures, running events, and giving participants a shared space to learn and work together. Administrators build lecture outlines and slide decks in the studio, then present them live to registered participants.

## Features

### Admin workspace

- Admin and superadmin accounts with role-based access.
- Lecture management with creator ownership, visibility, activation, and archive/restore workflows.
- Public lecture discovery with search and filters.
- Event management linked to private or public lectures, with event schedules, lecturers, locations, audiences, registration settings, participant limits, and optional groups.
- Event participant lists with group and attendance information.
- Event-scoped reset options for assessment submissions, group submissions and grades, registrations, or all event data.
- Admin management reserved for superadmins.

### Lecture studio and presentation

- Hierarchical lecture outlines with chapters, sections, topics, and decks.
- Slide types for information, picture-only, video, assessment, and group activity content.
- Multiple layouts for title, text, images, tables, graphic cards, and video.
- Private speaker notes, presentation controls, pause screens, and event-specific presenter views.
- Event backgrounds that carry across the slides in an event.
- Live assessment timers, answer collection, and participant performance summaries.

### Participant experience

- Event registration, group selection, upcoming events, and attendance history.
- Live presentation and master-screen views for each event.
- Group activity workspaces with shared submissions, member suggestions, leader review, and manual grading.
- Google and Facebook sign-in support when provider credentials are configured.

## Technology

- Laravel 13 and PHP 8.3 or newer
- SQLite by default; other Laravel-supported database connections can be configured
- Vite, Tailwind CSS 4, and vanilla JavaScript
- Laravel Socialite for optional social sign-in

## Local setup

Requirements: PHP 8.3+, Composer, Node.js, and npm.

```bash
composer install
cp .env.example .env
```

Edit `.env` for your environment. Set a `SUPER_ADMIN_BOOTSTRAP_PASSWORD` before seeding. For local development, set `APP_URL=http://localhost:8000`; if using social sign-in, configure matching callback URLs in both `.env` and the identity provider console.

```bash
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Open the URL printed by `php artisan serve`. The seeder creates the superadmin account and the default Cyber Tibay lecture and content. The superadmin email is `karlsteven.maddela@dict.gov.ph`; use the bootstrap password you set in `.env`.

Never commit `.env` or real credentials. Use `.env.example` to document environment variable names and safe defaults.

## About Karl

**Karl Steven A. Maddela, MIT** is a Project Development Officer III at the **Department of Information and Communications Technology (DICT), Regional Office 02**. He is the resource speaker featured in the Cyber Tibay lecture.
