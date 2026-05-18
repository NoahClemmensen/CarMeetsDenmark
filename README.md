# Car Meets Denmark

Web platform for the Danish car-meet community. Symfony 8 + PHP 8.4 + Hotwire (Turbo + Stimulus) + Tailwind, served from AssetMapper.

---

## Tech stack

- **Backend** — PHP 8.4, Symfony 8.0, Doctrine ORM 3
- **Frontend** — Hotwire Turbo 7, Stimulus 3, Tailwind 3 (via `symfonycasts/tailwind-bundle`), AssetMapper
- **Auth** — Symfony Security + form login + `symfonycasts/verify-email-bundle`
- **Database** — MySQL (the `.env.dev` default points at the team's dev DB; override locally)
- **Mail (dev)** — Mailpit via the included Docker compose

---

## Prerequisites

- PHP **8.4**
- Composer
- Docker (for local MySQL and/or Mailpit)
- Node not required. JS is shipped via importmap, CSS is built by `symfony console tailwind:build`

---

## Quick start

```bash
# 1. Get the code
git clone <repo>
cd CarMeetsDenmark

## Deploy the stack (See more details in /docker)
docker stack deploy -c docker/docker-stack.yml carmeets --with-registry-auth

## Remove stack
docker stack rm carmeets
```

You should now be able to register at `/register`, click the email-verify link from Mailpit, log in at `/login`, and complete profile setup at `/setup`.

---

## Common commands

| Task | Command |
|---|---|
| Apply pending migrations | `php bin/console doctrine:migrations:migrate` |
| Generate a new migration from entity changes | `php bin/console make:migration` |
| Generate a new entity / form / controller | `php bin/console make:<thing>` |
| Build / watch CSS | `php bin/console tailwind:build [--watch]` |
| Refresh installed importmap deps | `php bin/console importmap:install` |
| Add a new importmap dep | `php bin/console importmap:require <pkg>` |
| Inspect routes | `php bin/console debug:router` |
| Inspect services | `php bin/console debug:autowiring` |
| Clear cache | `php bin/console cache:clear` |
| Run tests (when present) | `php bin/phpunit` |

---

## Migrations

Schema changes live in `migrations/Version*.php`. After modifying an entity (`src/Entity/`), run `make:migration` to generate a diff, review it, then `migrate` to apply.

We use Doctrine migrations, not `doctrine:schema:update`, because every schema change should be reviewable in a versioned file, including in environments other than your own.

See `migrations/` for the existing files; each is named `Version<UTC timestamp>.php`.

---

## Where things live

```
src/
├── Controller/        ← thin HTTP handlers
├── Dto/               ← form-bound DTOs
├── Entity/            ← Doctrine entities (anaemic)
├── Enum/              ← closed sets (UserRole, ToastTypes)
├── EventSubscriber/   ← cross-cutting kernel listeners
├── Form/              ← form types
├── Form/Type/         ← custom field types
├── Http/              ← HTTP-layer helpers (TurboStreamHelper)
├── Repository/        ← Doctrine repositories
├── Security/          ← email verifier, redirect-target validation
├── Security/Voter/    ← role voters
├── Service/           ← business logic
└── Twig/              ← Twig extensions

templates/
├── components/        ← <twig:...> components (Modal:Shell, Modal:Header)
├── web/               ← web-platform templates (extends web/base.html.twig)
└── web/_turbo/        ← Turbo Stream partials (toast, flash)

assets/
├── app.js             ← (placeholder) entrypoint
├── web.js             ← real entrypoint: Turbo + Stimulus + custom actions
├── controllers/       ← Stimulus controllers
├── turbo-actions/     ← custom Turbo Stream actions (redirect, copy-to-clipboard)
├── utilities/         ← plain JS helpers (toast.js, clipboard.js)
└── styles/web/        ← BEM-ish CSS partials, imported by web.css
```

---
