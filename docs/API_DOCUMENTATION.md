# AlbumReviews RESTful API Documentation

**Version:** 1.0  
**Base URL:** `https://your-domain.com/api/v1`  
**Content Type:** `application/json`

---

## Table of Contents

1. [Introduction](#introduction)
2. [Authentication](#authentication)
3. [Albums Resource](#albums-resource)
4. [Reviews Resource](#reviews-resource)
5. [Music Information Endpoints](#music-information-endpoints)
6. [Error Handling](#error-handling)
7. [Rate Limiting](#rate-limiting)

---

## Introduction

The AlbumReviews API provides programmatic access to album and review data. This RESTful API follows industry best practices and conforms to REST architectural constraints as defined by Fielding (2000).

### Key Features
- Full CRUD operations for albums and reviews
- Sub-resource structure (reviews as children of albums)
- Token-based authentication
- External API integration (MusicBrainz, Discogs, OpenStreetMap)
- HATEOAS-compliant responses with `_links`

### Request Headers

All requests should include:

| Header | Value | Required |
|--------|-------|----------|
| `Accept` | `application/json` | Yes |
| `Content-Type` | `application/json` | For POST/PUT |
| `Authorization` | `Bearer {token}` | For protected endpoints |

---

## Authentication

The API uses JWT (JSON Web Tokens) for authentication. Tokens are signed using the HS256 algorithm and include standard claims (`iss`, `iat`, `exp`, `nbf`, `sub`).

### Login

Authenticate to receive a JWT token.

**Endpoint:** `POST /api/v1/auth/login`

**Request Body:**
```json
{
  "email": "user@example.com",
  "password": "yourpassword"
}
```

**Success Response (200 OK):**
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJhbGJ1bXJldmlld3MtYXBpIiwiaWF0IjoxNzA5NzM2MDAwLCJleHAiOjE3MDk3Mzk2MDAsInN1YiI6IjEiLCJlbWFpbCI6InVzZXJAZXhhbXBsZS5jb20ifQ.signature",
  "token_type": "Bearer",
  "expires_in": 3600,
  "expires_at": "2026-03-06T15:00:00+00:00",
  "user": {
    "id": 1,
    "email": "user@example.com",
    "username": "john_doe",
    "roles": ["ROLE_USER"]
  }
}
```

**Error Response (401 Unauthorized):**
```json
{
  "error": "Invalid credentials",
  "code": "INVALID_CREDENTIALS"
}
```

### Get Current User

**Endpoint:** `GET /api/v1/auth/me`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (200 OK):**
```json
{
  "user": {
    "id": 1,
    "email": "user@example.com",
    "username": "john_doe",
    "roles": ["ROLE_USER"],
    "createdAt": "2026-01-15T10:30:00+00:00"
  }
}
```

### Refresh Token

Obtain a new JWT token before the current one expires.

**Endpoint:** `POST /api/v1/auth/refresh`

**Headers:**
```
Authorization: Bearer {current_valid_token}
```

**Success Response (200 OK):**
```json
{
  "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "expires_at": "2026-03-06T16:00:00+00:00"
}
```

**Error Response (401 Unauthorized):**
```json
{
  "error": "Authentication required",
  "code": "UNAUTHORIZED"
}
```

---

## Albums Resource

### List All Albums

Retrieve a paginated list of all albums.

**Endpoint:** `GET /api/v1/albums`

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `genre` | string | - | Filter by genre |
| `limit` | integer | 50 | Maximum results (max: 100) |
| `offset` | integer | 0 | Pagination offset |

**Example Request:**
```bash
curl -X GET "https://api.example.com/api/v1/albums?genre=Rock&limit=10" \
  -H "Accept: application/json"
```

**Success Response (200 OK):**
```json
{
  "albums": [
    {
      "id": 1,
      "title": "Abbey Road",
      "artist": "The Beatles",
      "genre": "Rock",
      "releaseYear": 1969,
      "coverImage": "abbey-road.jpg",
      "trackList": ["Come Together", "Something", "Maxwell's Silver Hammer"],
      "slug": "the-beatles-abbey-road",
      "averageRating": 9.2,
      "reviewCount": 15,
      "createdAt": "2026-01-10T14:30:00+00:00",
      "updatedAt": null,
      "createdBy": {
        "id": 1,
        "username": "admin"
      },
      "_links": {
        "self": "https://api.example.com/api/v1/albums/1",
        "reviews": "https://api.example.com/api/v1/albums/1/reviews"
      }
    }
  ],
  "meta": {
    "total": 150,
    "limit": 10,
    "offset": 0
  }
}
```

---

### Get Single Album

Retrieve detailed information about a specific album.

**Endpoint:** `GET /api/v1/albums/{id}`

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `id` | integer | Album ID |

**Example Request:**
```bash
curl -X GET "https://api.example.com/api/v1/albums/1" \
  -H "Accept: application/json"
```

**Success Response (200 OK):**
```json
{
  "id": 1,
  "title": "Abbey Road",
  "artist": "The Beatles",
  "genre": "Rock",
  "releaseYear": 1969,
  "coverImage": "abbey-road.jpg",
  "trackList": ["Come Together", "Something", "Maxwell's Silver Hammer"],
  "slug": "the-beatles-abbey-road",
  "averageRating": 9.2,
  "reviewCount": 3,
  "createdAt": "2026-01-10T14:30:00+00:00",
  "updatedAt": null,
  "createdBy": {
    "id": 1,
    "username": "admin"
  },
  "reviews": [
    {
      "id": 1,
      "title": "Masterpiece!",
      "rating": 10,
      "content": "This album changed music forever...",
      "createdAt": "2026-01-15T09:00:00+00:00",
      "user": {
        "id": 2,
        "username": "music_fan"
      }
    }
  ],
  "_links": {
    "self": "https://api.example.com/api/v1/albums/1",
    "reviews": "https://api.example.com/api/v1/albums/1/reviews"
  }
}
```

**Error Response (404 Not Found):**
```json
{
  "error": "Album not found",
  "code": "ALBUM_NOT_FOUND"
}
```

---

### Create Album

Create a new album. Requires authentication.

**Endpoint:** `POST /api/v1/albums`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "title": "Dark Side of the Moon",
  "artist": "Pink Floyd",
  "genre": "Progressive Rock",
  "releaseYear": 1973,
  "trackList": "Speak to Me\nBreathe\nOn the Run\nTime"
}
```

**Field Validation:**

| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| `title` | string | Yes | Max 255 characters |
| `artist` | string | Yes | Max 255 characters |
| `genre` | string | Yes | Max 100 characters |
| `releaseYear` | integer | No | Valid year |
| `trackList` | string | No | Newline-separated tracks |

**Success Response (201 Created):**

Headers:
```
Location: https://api.example.com/api/v1/albums/25
```

Body:
```json
{
  "id": 25,
  "title": "Dark Side of the Moon",
  "artist": "Pink Floyd",
  "genre": "Progressive Rock",
  "releaseYear": 1973,
  "coverImage": null,
  "trackList": ["Speak to Me", "Breathe", "On the Run", "Time"],
  "slug": "pink-floyd-dark-side-of-the-moon",
  "averageRating": 0,
  "reviewCount": 0,
  "createdAt": "2026-03-06T14:00:00+00:00",
  "updatedAt": null,
  "createdBy": {
    "id": 1,
    "username": "john_doe"
  },
  "_links": {
    "self": "https://api.example.com/api/v1/albums/25",
    "reviews": "https://api.example.com/api/v1/albums/25/reviews"
  }
}
```

**Error Responses:**

401 Unauthorized:
```json
{
  "error": "Authentication required",
  "code": "UNAUTHORIZED"
}
```

400 Bad Request (Validation Error):
```json
{
  "error": "Validation failed",
  "messages": {
    "title": ["This value should not be blank."],
    "artist": ["This value should not be blank."]
  }
}
```

---

### Update Album

Update an existing album. Requires authentication and ownership or admin role.

**Endpoint:** `PUT /api/v1/albums/{id}`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body (partial update supported):**
```json
{
  "genre": "Art Rock",
  "releaseYear": 1973
}
```

**Success Response (200 OK):**
```json
{
  "id": 25,
  "title": "Dark Side of the Moon",
  "artist": "Pink Floyd",
  "genre": "Art Rock",
  "releaseYear": 1973,
  ...
}
```

**Error Response (403 Forbidden):**
```json
{
  "error": "Access denied",
  "code": "FORBIDDEN"
}
```

---

### Delete Album

Delete an album. Requires authentication and ownership or admin role.

**Endpoint:** `DELETE /api/v1/albums/{id}`

**Headers:**
```
Authorization: Bearer {token}
```

**Success Response (204 No Content):**
```
(empty body)
```

---

## Reviews Resource

Reviews are sub-resources of albums, following RESTful best practices for hierarchical relationships.

### List Reviews for Album

**Endpoint:** `GET /api/v1/albums/{albumId}/reviews`

**Success Response (200 OK):**
```json
{
  "reviews": [
    {
      "id": 1,
      "title": "Amazing Album",
      "content": "This album is a masterpiece that...",
      "rating": 9,
      "createdAt": "2026-02-15T10:30:00+00:00",
      "updatedAt": null,
      "user": {
        "id": 2,
        "username": "music_lover"
      },
      "_links": {
        "self": "https://api.example.com/api/v1/albums/1/reviews/1",
        "album": "https://api.example.com/api/v1/albums/1"
      }
    }
  ],
  "meta": {
    "total": 15,
    "albumId": 1,
    "albumTitle": "Abbey Road"
  }
}
```

---

### Get Single Review

**Endpoint:** `GET /api/v1/albums/{albumId}/reviews/{id}`

**Success Response (200 OK):**
```json
{
  "id": 1,
  "title": "Amazing Album",
  "content": "This album is a masterpiece that changed my perspective on music...",
  "rating": 9,
  "createdAt": "2026-02-15T10:30:00+00:00",
  "updatedAt": null,
  "user": {
    "id": 2,
    "username": "music_lover"
  },
  "album": {
    "id": 1,
    "title": "Abbey Road",
    "artist": "The Beatles",
    "slug": "the-beatles-abbey-road"
  },
  "_links": {
    "self": "https://api.example.com/api/v1/albums/1/reviews/1",
    "album": "https://api.example.com/api/v1/albums/1"
  }
}
```

---

### Create Review

**Endpoint:** `POST /api/v1/albums/{albumId}/reviews`

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "title": "Incredible Production Quality",
  "content": "The production on this album is absolutely stellar. Every track demonstrates careful attention to detail and the mixing creates an immersive listening experience that few albums can match.",
  "rating": 9
}
```

**Field Validation:**

| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| `title` | string | No | Max 255 characters |
| `content` | string | Yes | Min 50 characters |
| `rating` | integer | Yes | 1-10 |

**Success Response (201 Created):**

Headers:
```
Location: https://api.example.com/api/v1/albums/1/reviews/42
```

---

### Update Review

**Endpoint:** `PUT /api/v1/albums/{albumId}/reviews/{id}`

Requires authentication and review ownership or admin role.

---

### Delete Review

**Endpoint:** `DELETE /api/v1/albums/{albumId}/reviews/{id}`

Requires authentication and review ownership or admin role.

**Success Response:** `204 No Content`

---

## Music Information Endpoints

These endpoints consume external APIs to provide enhanced music information.

### Search Music

Search for albums using MusicBrainz or Discogs databases.

**Endpoint:** `GET /api/v1/music/search`

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `artist` | string | Yes | Artist name |
| `title` | string | No | Album title |
| `source` | string | No | `musicbrainz` (default) or `discogs` |

**Example Request:**
```bash
curl -X GET "https://api.example.com/api/v1/music/search?artist=Pink%20Floyd&title=The%20Wall" \
  -H "Accept: application/json"
```

**Success Response (200 OK):**
```json
{
  "source": "musicbrainz",
  "query": {
    "artist": "Pink Floyd",
    "title": "The Wall"
  },
  "results": [
    {
      "mbid": "3456789-abcd-efgh-ijkl",
      "title": "The Wall",
      "artist": "Pink Floyd",
      "releaseDate": "1979-11-30",
      "country": "GB",
      "trackCount": 26,
      "score": 100
    }
  ]
}
```

---

### Get Album Details from MusicBrainz

**Endpoint:** `GET /api/v1/music/album/{mbid}`

**Path Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `mbid` | string | MusicBrainz ID |

**Success Response (200 OK):**
```json
{
  "mbid": "3456789-abcd-efgh-ijkl",
  "title": "The Wall",
  "artist": "Pink Floyd",
  "releaseDate": "1979-11-30",
  "country": "GB",
  "barcode": "074643811224",
  "status": "Official",
  "label": "Harvest",
  "trackList": [
    {
      "position": 1,
      "title": "In the Flesh?",
      "length": 199000,
      "lengthFormatted": "3:19"
    }
  ],
  "trackCount": 26
}
```

---

### Find Purchase Options

Find where to buy vinyl/CD copies of an album.

**Endpoint:** `GET /api/v1/music/purchase`

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `artist` | string | Yes | Artist name |
| `title` | string | Yes | Album title |

**Success Response (200 OK):**
```json
{
  "album": {
    "artist": "Pink Floyd",
    "title": "The Wall"
  },
  "discogs": {
    "id": 123456,
    "title": "The Wall",
    "lowestPrice": 25.99,
    "numForSale": 1250,
    "marketplaceUrl": "https://www.discogs.com/sell/release/123456",
    "community": {
      "have": 150000,
      "want": 25000,
      "rating": 4.5
    }
  },
  "purchaseLinks": {
    "discogs": "https://www.discogs.com/sell/release/123456",
    "amazon": "https://www.amazon.com/s?k=Pink%20Floyd%20The%20Wall%20vinyl",
    "ebay": "https://www.ebay.com/sch/i.html?_nkw=Pink%20Floyd%20The%20Wall%20vinyl"
  }
}
```

---

### Find Record Stores

Find nearby record stores using OpenStreetMap data.

**Endpoint:** `GET /api/v1/music/stores`

**Query Parameters:**

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `location` | string | - | City, address, or postcode (required) |
| `radius` | integer | 10000 | Search radius in meters (max: 50000) |

**Example Request:**
```bash
curl -X GET "https://api.example.com/api/v1/music/stores?location=Manchester,%20UK&radius=15000" \
  -H "Accept: application/json"
```

**Success Response (200 OK):**
```json
{
  "query": {
    "location": "Manchester, UK",
    "radius": 15000
  },
  "location": {
    "lat": 53.4808,
    "lon": -2.2426,
    "displayName": "Manchester, Greater Manchester, England, United Kingdom"
  },
  "stores": [
    {
      "id": 12345678,
      "name": "Vinyl Exchange",
      "type": "music",
      "address": "18 Oldham Street, Manchester, M1 1JN",
      "coordinates": {
        "lat": 53.4832,
        "lon": -2.2356
      },
      "distance": {
        "meters": 580,
        "km": 0.58,
        "miles": 0.36
      },
      "contact": {
        "phone": "+44 161 228 1122",
        "website": "https://vinylexchange.co.uk",
        "email": null
      },
      "openingHours": "Mo-Sa 10:00-18:00; Su 11:00-17:00",
      "wheelchair": "yes",
      "osmUrl": "https://www.openstreetmap.org/node/12345678",
      "mapsUrl": "https://www.google.com/maps?q=53.4832,-2.2356"
    }
  ],
  "count": 8
}
```

---

### Find Stores by Coordinates

**Endpoint:** `GET /api/v1/music/stores/coordinates`

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `lat` | float | Yes | Latitude |
| `lon` | float | Yes | Longitude |
| `radius` | integer | No | Search radius in meters (default: 10000) |

---

## Error Handling

### HTTP Status Codes

| Code | Meaning | When Used |
|------|---------|-----------|
| 200 | OK | GET/PUT request successful |
| 201 | Created | POST request successful, resource created |
| 204 | No Content | DELETE successful |
| 400 | Bad Request | Invalid JSON or validation error |
| 401 | Unauthorized | Missing or invalid authentication |
| 403 | Forbidden | Authenticated but not authorized |
| 404 | Not Found | Resource does not exist |
| 500 | Server Error | Unexpected server error |

### Error Response Format

All error responses follow this structure:

```json
{
  "error": "Human-readable error message",
  "code": "MACHINE_READABLE_CODE",
  "detail": "Optional additional details",
  "messages": {
    "field_name": ["Validation error message"]
  }
}
```

---

## Rate Limiting

The API currently does not enforce rate limiting. However, when consuming external APIs (MusicBrainz, Discogs, OpenStreetMap), please be aware of their individual rate limits:

- **MusicBrainz:** 1 request per second
- **Discogs:** 60 requests per minute (unauthenticated)
- **OpenStreetMap Nominatim:** 1 request per second

---

---

## Creative Feature: Record Store Locator

The Record Store Locator is a unique feature that bridges digital album discovery with real-world purchasing. It combines multiple external APIs to help users find physical record stores near their location.

### How It Works

```
┌──────────────────┐     ┌──────────────────┐     ┌──────────────────┐
│   User enters    │────▶│   Nominatim API  │────▶│   Coordinates    │
│   location       │     │   (Geocoding)    │     │   (lat, lon)     │
└──────────────────┘     └──────────────────┘     └────────┬─────────┘
                                                           │
                                                           ▼
┌──────────────────┐     ┌──────────────────┐     ┌──────────────────┐
│   Sorted list    │◀────│   Distance calc  │◀────│   Overpass API   │
│   of stores      │     │   (Haversine)    │     │   (OSM Query)    │
└──────────────────┘     └──────────────────┘     └──────────────────┘
```

1. **Geocoding (Nominatim)**: Converts user's address/city into GPS coordinates
2. **Shop Query (Overpass)**: Searches OpenStreetMap for music shops within radius
3. **Distance Calculation**: Uses Haversine formula to calculate distances
4. **Results**: Returns sorted list with contact info, opening hours, and map links

### Web Interface

**URL:** `/stores`

**Features:**
- Search by city, address, or postcode
- View stores sorted by distance
- See contact details and opening hours
- Direct links to Google Maps for navigation
- Embedded OpenStreetMap view

### Purchase Options Integration

**URL:** `/album/{slug}/buy`

Each album page includes a "Where to Buy" section that:
- Searches Discogs marketplace for the album
- Shows lowest available price
- Displays collector statistics (have/want counts)
- Provides links to Discogs, Amazon, and eBay
- Links to the Record Store Locator

---

## Changelog

### Version 1.0 (March 2026)
- Initial API release
- Albums CRUD endpoints
- Reviews CRUD endpoints (as sub-resources)
- Token-based authentication
- MusicBrainz integration
- Discogs marketplace integration
- Record store locator with OpenStreetMap
- Album purchase options page

---

*Documentation generated for Advanced Web Development Assignment 2*
