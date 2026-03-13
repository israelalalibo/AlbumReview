# Implementation Walkthrough: RESTful API for Album Reviews

This document provides a detailed explanation of every change and implementation made for Assignment 2.

---

## Table of Contents

1. [Project Structure Overview](#1-project-structure-overview)
2. [API Form Types](#2-api-form-types)
3. [Album API Controller](#3-album-api-controller)
4. [Review API Controller](#4-review-api-controller)
5. [Authentication Controller](#5-authentication-controller)
6. [External API Services](#6-external-api-services)
7. [Music Information API Controller](#7-music-information-api-controller)
8. [Web Integration](#8-web-integration)
9. [Templates](#9-templates)
10. [Testing the API](#10-testing-the-api)

---

## 1. Project Structure Overview

### New Files Created

```
test1/
├── src/
│   ├── Controller/
│   │   ├── Api/
│   │   │   ├── AlbumApiController.php      # Albums CRUD API (GET, POST, PUT, DELETE)
│   │   │   ├── ReviewApiController.php     # Reviews CRUD API (sub-resource of albums)
│   │   │   ├── AuthApiController.php       # Token-based authentication
│   │   │   └── MusicInfoApiController.php  # External API endpoints
│   │   ├── MusicInfoController.php         # Web UI for music features (buy, stores)
│   │   └── JoindInController.php           # JoindIn API consumption (events, talks)
│   ├── Form/
│   │   ├── AlbumApiType.php                # Album validation (CSRF disabled for API)
│   │   └── ReviewApiType.php               # Review validation (CSRF disabled for API)
│   └── Service/
│       ├── MusicBrainzService.php          # MusicBrainz API client (metadata)
│       ├── DiscogsService.php              # Discogs marketplace client (purchase info)
│       └── RecordStoreLocatorService.php   # OpenStreetMap store finder (creative feature)
├── templates/
│   ├── joindin/
│   │   ├── events.html.twig                # List of past tech events
│   │   ├── event.html.twig                 # Single event details
│   │   └── talks.html.twig                 # Event talks with ratings
│   ├── music_info/
│   │   ├── buy.html.twig                   # Purchase options (Discogs integration)
│   │   └── stores.html.twig                # Record store locator (map + list)
│   ├── album/
│   │   └── show.html.twig                  # Updated with "Where to Buy" card
│   └── base.html.twig                      # Updated navbar with "Find Stores" link
└── docs/
    ├── API_DOCUMENTATION.md                # Full API documentation (766 lines)
    ├── CODE_REPORT.md                      # 1500-word academic code report
    └── IMPLEMENTATION_WALKTHROUGH.md       # This detailed walkthrough
```

### Routes Added

| Route | Method | Controller | Description |
|-------|--------|------------|-------------|
| `/api/v1/albums` | GET | AlbumApiController | List albums |
| `/api/v1/albums` | POST | AlbumApiController | Create album |
| `/api/v1/albums/{id}` | GET | AlbumApiController | Get album |
| `/api/v1/albums/{id}` | PUT | AlbumApiController | Update album |
| `/api/v1/albums/{id}` | DELETE | AlbumApiController | Delete album |
| `/api/v1/albums/{albumId}/reviews` | GET | ReviewApiController | List reviews |
| `/api/v1/albums/{albumId}/reviews` | POST | ReviewApiController | Create review |
| `/api/v1/albums/{albumId}/reviews/{id}` | GET | ReviewApiController | Get review |
| `/api/v1/albums/{albumId}/reviews/{id}` | PUT | ReviewApiController | Update review |
| `/api/v1/albums/{albumId}/reviews/{id}` | DELETE | ReviewApiController | Delete review |
| `/api/v1/auth/login` | POST | AuthApiController | Get token |
| `/api/v1/auth/me` | GET | AuthApiController | Get user info |
| `/api/v1/music/search` | GET | MusicInfoApiController | Search music |
| `/api/v1/music/album/{mbid}` | GET | MusicInfoApiController | Album details |
| `/api/v1/music/purchase` | GET | MusicInfoApiController | Purchase options |
| `/api/v1/music/stores` | GET | MusicInfoApiController | Find stores (API) |
| `/stores` | GET | MusicInfoController | Store locator (Web) |
| `/album/{slug}/buy` | GET | MusicInfoController | Purchase page (Web) |
| `/joindin/events` | GET | JoindInController | JoindIn events |
| `/joindin/event/{eventId}` | GET | JoindInController | Event details |
| `/joindin/event/{eventId}/talks` | GET | JoindInController | Event talks |

---

## 2. API Form Types

### Purpose
Form types validate incoming API data whilst disabling CSRF protection (not applicable to stateless API requests).

### AlbumApiType.php

**Location:** `src/Form/AlbumApiType.php`

```php
<?php
namespace App\Form;

use App\Entity\Album;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AlbumApiType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class)
            ->add('artist', TextType::class)
            ->add('genre', TextType::class)
            ->add('releaseYear', IntegerType::class, ['required' => false])
            ->add('trackList', TextareaType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Album::class,
            'csrf_protection' => false,  // Disabled for API
        ]);
    }
}
```

**How it works:**
1. `buildForm()` defines the fields that can be submitted via the API
2. Field types provide automatic type coercion (string → integer for releaseYear)
3. `data_class` binds form data to the Album entity
4. `csrf_protection => false` allows API clients without CSRF tokens

### ReviewApiType.php

Similar structure for reviews, validating:
- `title` (optional, max 255 chars)
- `content` (required, min 50 chars - constraint on entity)
- `rating` (required, 1-10 - constraint on entity)

---

## 3. Album API Controller

### Purpose
Provides RESTful CRUD operations for the Album resource.

**Location:** `src/Controller/Api/AlbumApiController.php`

### Route Structure

| Method | Route | Action | Description |
|--------|-------|--------|-------------|
| GET | `/api/v1/albums` | `list()` | List all albums with pagination |
| GET | `/api/v1/albums/{id}` | `show()` | Get single album with reviews |
| POST | `/api/v1/albums` | `create()` | Create new album (auth required) |
| PUT | `/api/v1/albums/{id}` | `update()` | Update album (owner/admin) |
| DELETE | `/api/v1/albums/{id}` | `delete()` | Delete album (owner/admin) |

### Key Implementation Details

#### 1. List Action with Filtering

```php
#[Route('/albums', name: 'api_albums_list', methods: ['GET'])]
public function list(Request $request, AlbumRepository $repository): JsonResponse
{
    $criteria = [];
    
    // Filter by genre if provided
    if ($genre = $request->query->get('genre')) {
        $criteria['genre'] = $genre;
    }
    
    $limit = min((int) $request->query->get('limit', 50), 100);  // Cap at 100
    $offset = (int) $request->query->get('offset', 0);
    
    $albums = $repository->findBy($criteria, ['createdAt' => 'DESC'], $limit, $offset);
    $total = $repository->count($criteria);
    
    return new JsonResponse([
        'albums' => array_map(fn($album) => $this->serializeAlbum($album), $albums),
        'meta' => ['total' => $total, 'limit' => $limit, 'offset' => $offset],
    ], Response::HTTP_OK);
}
```

**Explanation:**
- Query parameters extracted from `$request->query`
- Limit capped at 100 to prevent abuse
- Repository's `findBy()` handles filtering and pagination
- `count()` provides total for pagination UI

#### 2. Create Action with Validation

```php
#[Route('/albums', name: 'api_albums_create', methods: ['POST'])]
public function create(Request $request, EntityManagerInterface $em): JsonResponse
{
    // Step 1: Check authentication
    if (!$this->getUser()) {
        return new JsonResponse(
            ['error' => 'Authentication required', 'code' => 'UNAUTHORIZED'],
            Response::HTTP_UNAUTHORIZED
        );
    }
    
    // Step 2: Parse JSON body
    $content = $request->getContent();
    $data = json_decode($content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new JsonResponse(
            ['error' => 'Invalid JSON', 'detail' => json_last_error_msg()],
            Response::HTTP_BAD_REQUEST
        );
    }
    
    // Step 3: Validate using form
    $album = new Album();
    $form = $this->createForm(AlbumApiType::class, $album);
    $form->submit($data);
    
    if (!$form->isValid()) {
        $errors = $this->getFormErrors($form);
        return new JsonResponse(
            ['error' => 'Validation failed', 'messages' => $errors],
            Response::HTTP_BAD_REQUEST
        );
    }
    
    // Step 4: Persist
    $album->setCreatedBy($this->getUser());
    $em->persist($album);
    $em->flush();
    
    // Step 5: Return 201 Created with Location header
    $location = $this->generateUrl(
        'api_albums_show',
        ['id' => $album->getId()],
        UrlGeneratorInterface::ABSOLUTE_URL
    );
    
    return new JsonResponse(
        $this->serializeAlbum($album),
        Response::HTTP_CREATED,
        ['Location' => $location]
    );
}
```

**Flow explanation:**
1. **Authentication check** - Returns 401 if no user logged in
2. **JSON parsing** - Raw body decoded with error handling
3. **Form validation** - `submit()` populates entity and triggers validation
4. **Persistence** - Sets creator relationship before saving
5. **Response** - 201 status with Location header per REST standards

#### 3. Serialisation Method

```php
private function serializeAlbum(Album $album, bool $includeReviews = false): array
{
    $data = [
        'id' => $album->getId(),
        'title' => $album->getTitle(),
        'artist' => $album->getArtist(),
        'genre' => $album->getGenre(),
        'releaseYear' => $album->getReleaseYear(),
        'coverImage' => $album->getCoverImage(),
        'trackList' => $album->getTrackListAsArray(),
        'slug' => $album->getSlug(),
        'averageRating' => $album->getAverageRating(),
        'reviewCount' => $album->getReviews()->count(),
        'createdAt' => $album->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        'updatedAt' => $album->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
        'createdBy' => [
            'id' => $album->getCreatedBy()?->getId(),
            'username' => $album->getCreatedBy()?->getUsername(),
            // Note: NO password or email exposed
        ],
        '_links' => [
            'self' => $this->generateUrl('api_albums_show', ['id' => $album->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            'reviews' => $this->generateUrl('api_reviews_list', ['albumId' => $album->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
        ],
    ];
    
    // Optionally include full review details
    if ($includeReviews) {
        $data['reviews'] = array_map(...);
    }
    
    return $data;
}
```

**Key points:**
- Excludes sensitive data (passwords, emails)
- Includes computed fields (averageRating, reviewCount)
- `_links` implements HATEOAS for discoverability
- ISO 8601 date formatting (ATOM constant)

---

## 4. Review API Controller

### Purpose
Manages reviews as sub-resources of albums, demonstrating RESTful hierarchy.

**Location:** `src/Controller/Api/ReviewApiController.php`

### Route Structure

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/api/v1/albums/{albumId}/reviews` | List reviews for album |
| GET | `/api/v1/albums/{albumId}/reviews/{id}` | Get single review |
| POST | `/api/v1/albums/{albumId}/reviews` | Create review |
| PUT | `/api/v1/albums/{albumId}/reviews/{id}` | Update review |
| DELETE | `/api/v1/albums/{albumId}/reviews/{id}` | Delete review |

### Sub-Resource Pattern

Reviews are nested under albums because:
1. Reviews cannot exist without an album
2. URL structure reflects domain relationship
3. Allows filtering reviews by album implicitly

```php
#[Route('/albums/{albumId}/reviews', name: 'api_reviews_list', methods: ['GET'])]
public function list(int $albumId, AlbumRepository $albumRepository): JsonResponse
{
    // First validate the parent album exists
    $album = $albumRepository->find($albumId);
    
    if (!$album) {
        return new JsonResponse(
            ['error' => 'Album not found', 'code' => 'ALBUM_NOT_FOUND'],
            Response::HTTP_NOT_FOUND
        );
    }
    
    // Get reviews through the relationship
    $reviews = array_map(
        fn($review) => $this->serializeReview($review),
        $album->getReviews()->toArray()
    );
    
    return new JsonResponse([
        'reviews' => $reviews,
        'meta' => [
            'total' => count($reviews),
            'albumId' => $albumId,
            'albumTitle' => $album->getTitle(),
        ],
    ], Response::HTTP_OK);
}
```

---

## 5. Authentication Controller

### Purpose
Provides JWT-based authentication for API clients using industry-standard JSON Web Tokens.

**Location:** `src/Controller/Api/AuthApiController.php`

### JWT Token Generation

The authentication system uses the `firebase/php-jwt` library with a dedicated `JwtService`:

```php
// src/Service/JwtService.php
class JwtService
{
    private const ALGORITHM = 'HS256';
    private const TOKEN_TTL = 3600; // 1 hour

    public function createToken(User $user): string
    {
        $payload = [
            'iss' => $this->issuer,           // Issuer
            'iat' => time(),                   // Issued at
            'exp' => time() + self::TOKEN_TTL, // Expiration
            'nbf' => time(),                   // Not before
            'sub' => (string) $user->getId(),  // Subject (user ID)
            'email' => $user->getEmail(),
            'username' => $user->getUsername(),
            'roles' => $user->getRoles(),
        ];

        return JWT::encode($payload, $this->secretKey, self::ALGORITHM);
    }
}
```

The `AuthApiController` uses this service:

```php
#[Route('/auth/login', name: 'api_auth_login', methods: ['POST'])]
public function login(Request $request, UserRepository $userRepository, 
                      UserPasswordHasherInterface $passwordHasher): JsonResponse
{
    // Validate credentials...
    $user = $userRepository->findOneBy(['email' => $email]);
    
    if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
        return new JsonResponse(
            ['error' => 'Invalid credentials'],
            Response::HTTP_UNAUTHORIZED
        );
    }
    
    // Generate JWT token
    $token = $this->jwtService->createToken($user);
    
    return new JsonResponse([
        'token' => $token,  // e.g., eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
        'token_type' => 'Bearer',
        'expires_in' => $this->jwtService->getTokenTtl(),
    ]);
}
```

### Token Validation

Symfony's `access_token` authenticator handles validation via `ApiTokenHandler`:

```php
// src/Security/ApiTokenHandler.php
class ApiTokenHandler implements AccessTokenHandlerInterface
{
    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        $payload = $this->jwtService->decodeToken($accessToken);
        
        if ($payload === null) {
            throw new BadCredentialsException('Invalid or expired JWT token');
        }
        
        $user = $this->userRepository->find($payload['sub']);
        return new UserBadge($user->getUserIdentifier());
    }
}
```

**Security features:**
- Industry-standard JWT format (RFC 7519)
- HS256 algorithm with secure signature
- Standard claims: `iss`, `iat`, `exp`, `nbf`, `sub`
- Automatic expiration validation
- Token refresh endpoint available at `/api/v1/auth/refresh`

---

## 6. External API Services

### 6.1 MusicBrainzService

**Purpose:** Retrieve album metadata from MusicBrainz database.

**Location:** `src/Service/MusicBrainzService.php`

```php
public function searchAlbum(string $artist, string $title, int $limit = 10): array
{
    try {
        $query = sprintf('artist:"%s" AND release:"%s"', $artist, $title);
        
        $response = $this->client->get('release', [
            'query' => [
                'query' => $query,
                'fmt' => 'json',
                'limit' => $limit,
            ],
        ]);

        $data = json_decode($response->getBody()->getContents(), true);
        return $this->formatReleaseResults($data['releases'] ?? []);
        
    } catch (GuzzleException $e) {
        return ['error' => $e->getMessage()];
    }
}
```

**How Guzzle is used:**
1. Client configured with base URI and headers in constructor
2. `get()` method sends HTTP GET request
3. Query parameters passed in `'query'` option
4. Response body streamed and decoded as JSON
5. GuzzleException caught for network errors

### 6.2 DiscogsService

**Purpose:** Find vinyl/CD marketplace listings and pricing.

**Key method:** `findAlbumWithPurchaseOptions()`

```php
public function findAlbumWithPurchaseOptions(string $artist, string $title): ?array
{
    // Step 1: Search for the release
    $searchResults = $this->searchRelease($artist, $title);
    
    if (empty($searchResults) || isset($searchResults['error'])) {
        return null;
    }
    
    // Step 2: Get detailed info for first match
    $firstResult = $searchResults[0];
    $details = $this->getReleaseDetails($firstResult['id']);
    
    return $details;
}
```

**Data extracted:**
- Lowest marketplace price
- Number of copies for sale
- Collector statistics (have/want counts)
- Community rating
- Direct marketplace URL

### 6.3 RecordStoreLocatorService

**Purpose:** Find physical record stores near a location.

**APIs used:**
1. **Nominatim** - Geocodes addresses to coordinates
2. **Overpass** - Queries OpenStreetMap for shops

```php
public function findStoresByLocation(string $location, int $radius = 10000): array
{
    // Step 1: Geocode the location
    $coords = $this->geocodeAddress($location);
    
    if (!$coords) {
        return ['error' => 'Could not find location'];
    }
    
    // Step 2: Query Overpass for music shops
    $stores = $this->findNearbyRecordStores($coords['lat'], $coords['lon'], $radius);
    
    return [
        'location' => $coords,
        'stores' => $stores,
        'count' => count($stores),
    ];
}
```

**Overpass query explained:**

```
[out:json][timeout:25];
(
  node["shop"="music"](around:10000,53.48,-2.24);
  node["shop"="records"](around:10000,53.48,-2.24);
  node["shop"="hifi"](around:10000,53.48,-2.24);
);
out body;
```

This queries OpenStreetMap for:
- `shop=music` - Music stores
- `shop=records` - Record shops
- `shop=hifi` - Hi-fi/audio stores

Within the specified radius around the coordinates.

---

## 7. Music Information API Controller

**Location:** `src/Controller/Api/MusicInfoApiController.php`

Exposes the service methods as API endpoints:

| Endpoint | Service | Purpose |
|----------|---------|---------|
| `GET /api/v1/music/search` | MusicBrainz | Search albums |
| `GET /api/v1/music/album/{mbid}` | MusicBrainz | Get album details |
| `GET /api/v1/music/purchase` | Discogs | Find purchase options |
| `GET /api/v1/music/stores` | RecordStoreLocator | Find nearby stores |

---

## 8. Web Integration

### MusicInfoController

**Location:** `src/Controller/MusicInfoController.php`

Integrates external API data into the web UI:

```php
#[Route('/album/{slug}/buy', name: 'album_buy', methods: ['GET'])]
public function buyAlbum(string $slug, AlbumRepository $albumRepository): Response
{
    $album = $albumRepository->findOneBy(['slug' => $slug]);
    
    if (!$album) {
        throw $this->createNotFoundException('Album not found');
    }
    
    // Fetch purchase info from Discogs
    $discogsService = new DiscogsService();
    $purchaseInfo = $discogsService->findAlbumWithPurchaseOptions(
        $album->getArtist(),
        $album->getTitle()
    );
    
    return $this->render('music_info/buy.html.twig', [
        'album' => $album,
        'purchaseInfo' => $purchaseInfo,
    ]);
}
```

---

## 9. Templates

### buy.html.twig

Displays:
- Album cover and details
- Discogs marketplace price
- Collector statistics
- Links to Discogs, Amazon, eBay
- Link to record store finder

### stores.html.twig

Features:
- Location search form
- List of nearby stores with:
  - Distance
  - Contact info
  - Opening hours
  - Google Maps links
- Embedded OpenStreetMap view

---

## 10. Testing the API

### Using cURL

**List albums:**
```bash
curl -X GET http://localhost:8000/api/v1/albums
```

**Create album (with auth):**
```bash
# First, login to get token
TOKEN=$(curl -s -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"admin"}' | jq -r '.token')

# Then create album
curl -X POST http://localhost:8000/api/v1/albums \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN" \
  -d '{
    "title": "The Dark Side of the Moon",
    "artist": "Pink Floyd",
    "genre": "Progressive Rock",
    "releaseYear": 1973
  }'
```

**Find record stores:**
```bash
curl "http://localhost:8000/api/v1/music/stores?location=Manchester,UK"
```

### Using Postman

1. Import the API endpoints
2. Set up environment variable for `base_url`
3. Create login request to get token
4. Use token in Authorization header for protected endpoints

---

---

## 11. Creative Feature: Record Store Locator

### Overview

The Record Store Locator is a standout feature that differentiates this application by bridging digital album discovery with physical purchasing. It answers the question: "I love this album, where can I buy a physical copy?"

### How It Works (Technical Flow)

```
User Journey:
┌─────────────────────────────────────────────────────────────────────┐
│  1. User views album page                                           │
│          ↓                                                          │
│  2. Clicks "Where to Buy" button                                    │
│          ↓                                                          │
│  3. DiscogsService searches marketplace                             │
│          ↓                                                          │
│  4. Shows prices, availability, and purchase links                  │
│          ↓                                                          │
│  5. User clicks "Find Stores Near Me"                               │
│          ↓                                                          │
│  6. Enters location (e.g., "Manchester, UK")                        │
│          ↓                                                          │
│  7. NominatimAPI geocodes address → coordinates (53.48, -2.24)      │
│          ↓                                                          │
│  8. OverpassAPI queries OpenStreetMap for shops within 15km         │
│          ↓                                                          │
│  9. Results sorted by distance using Haversine formula              │
│          ↓                                                          │
│  10. User sees list of stores with map, contact info, directions    │
└─────────────────────────────────────────────────────────────────────┘
```

### Services Involved

#### 1. DiscogsService (Purchase Options)

```php
// src/Service/DiscogsService.php

public function findAlbumWithPurchaseOptions(string $artist, string $title): ?array
{
    // Search Discogs database for the album
    $searchResults = $this->searchRelease($artist, $title);
    
    if (empty($searchResults)) {
        return null;
    }
    
    // Get detailed marketplace info
    $details = $this->getReleaseDetails($searchResults[0]['id']);
    
    // Returns: lowestPrice, numForSale, community stats, marketplace URL
    return $details;
}
```

**Data Retrieved:**
- `lowestPrice` - Cheapest copy currently for sale
- `numForSale` - Number of sellers
- `community.have` - How many collectors own it
- `community.want` - How many collectors want it
- `marketplaceUrl` - Direct link to Discogs listings

#### 2. RecordStoreLocatorService (Store Finder)

```php
// src/Service/RecordStoreLocatorService.php

public function findStoresByLocation(string $location, int $radius = 10000): array
{
    // Step 1: Geocode the location using Nominatim
    $coords = $this->geocodeAddress($location);
    // Returns: ['lat' => 53.4808, 'lon' => -2.2426, 'displayName' => '...']
    
    // Step 2: Query Overpass API for music shops
    $stores = $this->findNearbyRecordStores($coords['lat'], $coords['lon'], $radius);
    
    return [
        'location' => $coords,
        'stores' => $stores,  // Sorted by distance
        'count' => count($stores),
    ];
}
```

**Overpass Query Explained:**

```
[out:json][timeout:25];
(
  node["shop"="music"](around:10000,53.48,-2.24);    // Music stores
  node["shop"="records"](around:10000,53.48,-2.24);  // Record shops
  node["shop"="hifi"](around:10000,53.48,-2.24);     // Hi-fi/audio
);
out body;
```

This queries OpenStreetMap's database for any business tagged as a music, records, or hi-fi shop within the specified radius.

#### 3. Haversine Distance Calculation

```php
private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
{
    $earthRadius = 6371000; // meters
    
    $lat1Rad = deg2rad($lat1);
    $lat2Rad = deg2rad($lat2);
    $deltaLat = deg2rad($lat2 - $lat1);
    $deltaLon = deg2rad($lon2 - $lon1);
    
    $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
         cos($lat1Rad) * cos($lat2Rad) *
         sin($deltaLon / 2) * sin($deltaLon / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earthRadius * $c;  // Distance in meters
}
```

The Haversine formula calculates the great-circle distance between two points on a sphere (Earth), accounting for the planet's curvature.

### User Interface

#### Album Page Integration (`templates/album/show.html.twig`)

A green card appears on every album page:

```twig
<div class="card mb-4 border-success">
    <div class="card-body">
        <h5 class="card-title text-success">
            <i class="bi bi-cart-check"></i> Want to own this album?
        </h5>
        <p class="card-text">Find vinyl records, CDs, and local record stores.</p>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ path('album_buy', {slug: album.slug}) }}" class="btn btn-success">
                <i class="bi bi-vinyl"></i> Where to Buy
            </a>
            <a href="{{ path('record_stores') }}" class="btn btn-outline-success">
                <i class="bi bi-geo-alt"></i> Find Stores Near Me
            </a>
        </div>
    </div>
</div>
```

#### Purchase Page (`templates/music_info/buy.html.twig`)

Shows:
- Album cover and details
- Discogs lowest price with "copies for sale" count
- Collector statistics (have/want/rating)
- Direct links to Discogs, Amazon, eBay
- Link to Record Store Locator

#### Store Locator (`templates/music_info/stores.html.twig`)

Shows:
- Search form for location input
- List of stores sorted by distance
- Store details: name, address, phone, website, opening hours
- "Open in Google Maps" buttons
- Embedded OpenStreetMap showing the search location

### Why This Feature Stands Out

1. **Real-World Value**: Connects online research to physical purchasing
2. **Multiple API Integration**: Combines Discogs, Nominatim, and Overpass APIs
3. **Complex Data Processing**: Geocoding, spatial queries, distance calculations
4. **User-Centric Design**: Intuitive flow from album discovery to store finding
5. **No API Keys Required**: Uses open APIs (OpenStreetMap is free)

---

## Summary

This implementation provides:

✅ **API Implementation (35%)**
- Full CRUD for albums and reviews
- RESTful design with proper verbs and status codes
- Sub-resource structure for reviews
- Token-based authentication
- HATEOAS links

✅ **API Consumption (35%)**
- MusicBrainz for album metadata
- Discogs for marketplace/purchase info
- OpenStreetMap for record store locations
- Guzzle HTTP client throughout
- **Creative Feature: Record Store Locator with multi-API integration**

✅ **Documentation (30%)**
- Comprehensive API documentation
- 1500-word code report with references
- This implementation walkthrough

The creative record store locator feature provides unique value by connecting digital album discovery with real-world purchasing opportunities, demonstrating advanced API consumption techniques whilst delivering genuine user functionality.
