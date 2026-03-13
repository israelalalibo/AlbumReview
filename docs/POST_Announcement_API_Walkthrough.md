# Step-by-step: POST endpoint to create an announcement

This implements the workshop task: **implement an endpoint to create a new announcement** (POST, JSON, validation via form, 400 on error, 201 + Location on success).

---

## Overview

- **URL:** `POST /api/v1/announcements`
- **Request body:** JSON, e.g. `{"message": "Your text"}`
- **Success:** 201 Created, body = created announcement, header `Location` = URL to GET it
- **Error:** 400 Bad Request if JSON is invalid or validation fails

---

## Step 1: Create an API-specific form type (AnnouncementAPIType)

**Why:** The web form (`AnnouncementType`) uses **CSRF protection**. API clients (Postman, mobile apps, other services) don’t send CSRF tokens, so we use a separate form type for the API with CSRF **disabled**.

**What we did:**

- Added **`src/Form/AnnouncementAPIType.php`**.
- Same fields as `AnnouncementType` (e.g. `message`), same `data_class` => `Announcement`.
- In `configureOptions` we set **`'csrf_protection' => false`** so the API doesn’t require a CSRF token.

**Result:** Validation rules are the same as the web form, but the API form can be submitted without a `_token` field.

---

## Step 2: Check we can parse the POST data (expect JSON)

**Why:** The API returns JSON, so we expect the client to send JSON in the body. If the body isn’t valid JSON, we can’t validate or create an announcement.

**What we do in the controller:**

1. Get the raw body: **`$request->getContent()`**.
2. Decode it: **`$data = json_decode($content, true)`** (`true` = associative array).
3. Check for errors: **`json_last_error() !== \JSON_ERROR_NONE`**.
4. If there was an error, return **400 Bad Request** with a message (e.g. “Invalid JSON” and `json_last_error_msg()`).

**Result:** If we continue past this block, `$data` is a PHP array (e.g. `['message' => 'Hello']`) and we can pass it to the form.

---

## Step 3: Check that the POST data meets validations (using the form)

**Why:** We reuse Symfony’s form to validate the same rules as the web app (e.g. required message, max length) without duplicating logic.

**What we do:**

1. Create a new **`Announcement`** entity and an **`AnnouncementAPIType`** form bound to it.
2. **Submit the decoded data to the form:** `$form->submit($data)`.
   - The form expects keys that match field names (e.g. `message`), so `$data` must be like `['message' => '...']`.
3. If the form is **not valid** (`!$form->isValid()`):
   - Collect errors (e.g. with `$form->getErrors(true)`).
   - Return **400 Bad Request** with a JSON body that describes the validation errors.

**Result:** If we continue, the form is valid and the `Announcement` entity has been updated with the submitted (and validated) data.

---

## Step 4: If either parsing or validation fails → 400

**Why:** The workshop says: if we can’t parse the body or validation fails, respond with status **400**.

**What we did:**

- After the JSON check: return `new JsonResponse(..., Response::HTTP_BAD_REQUEST)` (400).
- After the form validation check: same thing, with an error payload (e.g. `['error' => 'Validation failed', 'messages' => [...]]`).

**Result:** Clients get a clear 400 and a JSON body explaining the problem.

---

## Step 5: If everything is OK — create entity and persist (same as web interface)

**Why:** Business logic should match the web app: one new announcement, with a timestamp, saved to the database.

**What we do:**

1. **Set timestamp:** `$announcement->setTimestamp(new \DateTime())` (same as in `AnnouncementController`).
2. **Persist and flush:** `$entityManager->persist($announcement);` then `$entityManager->flush();`

**Result:** The new announcement exists in the database and has an `id`.

---

## Step 6: Return 201 Created and the URL in the Location header

**Why:** REST convention for “resource created” is **201 Created**. The **Location** header tells the client where to GET the new resource.

**What we do:**

1. **Status code:** `Response::HTTP_CREATED` (201).
2. **Location header:** URL to the **GET** endpoint for this announcement.
   - Route name: `api_announcement_show`, with parameter `id` = `$announcement->getId()`.
   - We use **`$this->generateUrl(..., UrlGeneratorInterface::ABSOLUTE_URL)`** so the header is a full URL (e.g. `http://localhost:8000/api/v1/announcements/5`).
3. **Response body:** JSON with the created announcement (id, message, timestamp), same shape as your GET single-announcement response.

**Result:** Client gets 201, a body with the new announcement, and a `Location` header it can use to fetch the resource.

---

## Summary flow (in code order)

1. Parse JSON → if invalid, **400**.
2. Submit decoded data to **AnnouncementAPIType** → if invalid, **400**.
3. Set timestamp, persist, flush (same as web).
4. Return **201** + JSON body + **Location** header.

---

## How to test

Replace `http://localhost:8000` with your actual base URL if different (e.g. `http://127.0.0.1:8000` or your Symfony server URL).

---

### Using curl

**1. Valid request (expect 201 + Location + JSON body)**

```bash
curl -X POST http://localhost:8000/api/v1/announcements ^
  -H "Content-Type: application/json" ^
  -d "{\"message\": \"Hello from the API\"}"
```

- **Windows (CMD):** use `^` at the end of each line for continuation, and escape double quotes in the body as `\"`.
- **Windows (PowerShell):** use backtick `` ` `` for line continuation and single quotes for the JSON so you don’t need to escape:

```powershell
curl.exe -X POST http://localhost:8000/api/v1/announcements `
  -H "Content-Type: application/json" `
  -d '{"message": "Hello from the API"}'
```

- **Linux / macOS:**

```bash
curl -X POST http://localhost:8000/api/v1/announcements \
  -H "Content-Type: application/json" \
  -d '{"message": "Hello from the API"}'
```

**Check:** Status **201**, response body has `id`, `message`, `timestamp`. To see response headers (including `Location`) with curl, add `-i`:

```bash
curl -i -X POST http://localhost:8000/api/v1/announcements -H "Content-Type: application/json" -d "{\"message\": \"Test\"}"
```

---

**2. Invalid JSON (expect 400)**

```bash
curl -X POST http://localhost:8000/api/v1/announcements ^
  -H "Content-Type: application/json" ^
  -d "not json at all"
```

PowerShell:

```powershell
curl.exe -X POST http://localhost:8000/api/v1/announcements -H "Content-Type: application/json" -d "not json at all"
```

**Check:** Status **400**, body similar to `{"error":"Invalid JSON","detail":"..."}`.

---

**3. Validation error — missing message (expect 400)**

```bash
curl -X POST http://localhost:8000/api/v1/announcements ^
  -H "Content-Type: application/json" ^
  -d "{}"
```

PowerShell:

```powershell
curl.exe -X POST http://localhost:8000/api/v1/announcements -H "Content-Type: application/json" -d '{}'
```

**Check:** Status **400**, body contains validation messages (e.g. `"error":"Validation failed"`, `"messages":[...]`).

---

**4. GET the created announcement using Location**

After a successful POST, copy the `Location` header value (e.g. `http://localhost:8000/api/v1/announcements/1`) and run:

```bash
curl http://localhost:8000/api/v1/announcements/1
```

**Check:** Status **200**, body is the same announcement (id, message, timestamp).

---

### Using Postman

**Setup (same for all tests)**

1. Open Postman and create a **new request**.
2. Set **Method** to **POST**.
3. Set **URL** to: `http://localhost:8000/api/v1/announcements` (adjust host/port if needed).
4. Open the **Headers** tab and add:
   - **Key:** `Content-Type`  
   - **Value:** `application/json`
5. Open the **Body** tab, select **raw**, and choose **JSON** in the dropdown next to it.

---

**Test 1: Valid request (expect 201 + Location)**

1. In **Body**, enter:

   ```json
   {"message": "Hello from Postman"}
   ```

2. Click **Send**.

**Check:**

- **Status:** `201 Created`.
- **Headers** (Headers tab in response): `Location` = `http://localhost:8000/api/v1/announcements/<id>` (or your base URL + path + id).
- **Body** (Pretty/raw): JSON with `id`, `message`, and `timestamp`.

---

**Test 2: Invalid JSON (expect 400)**

1. In **Body**, change to invalid JSON, e.g.:

   ```
   not json at all
   ```

2. Click **Send**.

**Check:**

- **Status:** `400 Bad Request`.
- **Body:** e.g. `{"error":"Invalid JSON","detail":"Syntax error"}` (exact message may vary).

---

**Test 3: Validation error — missing message (expect 400)**

1. In **Body**, set:

   ```json
   {}
   ```

2. Click **Send**.

**Check:**

- **Status:** `400 Bad Request`.
- **Body:** Contains `"error":"Validation failed"` and a `"messages"` array with validation errors.

---

**Test 4: GET the created resource**

1. Create a **new request** (or duplicate the current one).
2. Set **Method** to **GET**.
3. Set **URL** to the **Location** value from Test 1 (e.g. `http://localhost:8000/api/v1/announcements/1`).
4. Remove or leave **Body** empty; no `Content-Type` header needed for GET.
5. Click **Send**.

**Check:**

- **Status:** `200 OK`.
- **Body:** Same announcement as in the 201 response (id, message, timestamp).

---

**Quick reference**

| Test            | Method | URL                                      | Body                          | Expected status |
|-----------------|--------|------------------------------------------|-------------------------------|-----------------|
| Valid create    | POST   | `/api/v1/announcements`                  | `{"message": "Some text"}`    | 201             |
| Invalid JSON    | POST   | `/api/v1/announcements`                  | `not json`                    | 400             |
| Validation fail | POST   | `/api/v1/announcements`                  | `{}`                          | 400             |
| Get one         | GET    | `/api/v1/announcements/{id}` (from Location) | (none)                    | 200             |

---

## Files touched

- **`src/Form/AnnouncementAPIType.php`** — new form type, CSRF off, same fields as web.
- **`src/Controller/APIController.php`** — new method `createAnnouncement` (POST `/api/v1/announcements`) implementing the steps above.
