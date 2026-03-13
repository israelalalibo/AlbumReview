# Step 3: Create an API Controller — Walkthrough

This matches the workshop instruction: *Create a new controller called APIController that extends AbstractFOSRestController and enables REST annotations.*

---

## What you need

- **FOSRestBundle** — already in your project (`friendsofsymfony/rest-bundle` in `composer.json`).
- **APIController** — in `src/Controller/APIController.php` (we’ll fix and complete it below).

---

## Step-by-step

### 1. Create the controller file (if it doesn’t exist)

- Path: `src/Controller/APIController.php`
- You already have this file; we’ll adjust it so it matches the workshop and works with your routing.

### 2. Use the correct base class

The workshop says: *This new controller should extend AbstractFOSRestController.*

- **Why:** `AbstractFOSRestController` gives you helpers for REST (e.g. `view()` for JSON responses) and is the base class FOS Rest expects for REST endpoints.
- In code: the class declaration must be  
  `class APIController extends AbstractFOSRestController`

### 3. Add the REST annotations use statement

The workshop says: *Enable Rest annotations by adding:  
`use FOS\RestBundle\Controller\Annotations as Rest;`*

- **Why:** The `Rest` namespace provides the annotation/attribute classes for HTTP methods:
  - `Rest\Get`  → GET
  - `Rest\Post` → POST
  - `Rest\Put`  → PUT
  - `Rest\Delete` → DELETE  
  So you can mark your methods with the right HTTP verb.
- In code: at the top of the controller, with the other `use` lines, add:
  ```php
  use FOS\RestBundle\Controller\Annotations as Rest;
  ```

### 4. Use the annotations/attributes on your methods

- You can use either:
  - **Docblock (annotation):** `/** @Rest\Get("/api/v1/announcements") */`
  - **PHP 8 attribute:** `#[Rest\Get("/api/v1/announcements")]`
- Your project uses **attributes** elsewhere, so we use the attribute form for consistency.
- **Note:** In FOSRestBundle 3, the bundle’s automatic REST route loader is disabled. So we also use Symfony’s standard `#[Route(...)]` so the route is actually registered. The `Rest` use is still required by the workshop and keeps the REST meaning clear; you can use `#[Route(..., methods: ['GET'])]` for the same path so the URL works.

---

## Summary checklist

- [x] Controller exists: `App\Controller\APIController`
- [x] It extends `AbstractFOSRestController`
- [x] It has `use FOS\RestBundle\Controller\Annotations as Rest;`
- [x] REST “annotations” are enabled (via `Rest` and, for routing, `Route` with the correct method)

---

## How to test

1. Start the app (e.g. `symfony serve` or your web server).
2. Open or request: `http://localhost:8000/api/v1/announcements` (or your base URL + `/api/v1/announcements`).
3. You should get a JSON list of announcements (or `[]` if there are none).
4. Optional: run `php bin/console debug:router` and confirm there is a route for `/api/v1/announcements` (GET).

Once this works, you can add more endpoints (e.g. one announcement by id) following the same pattern.
