# API Spec

This API powers the AI Plagiarism Checker. All responses are JSON unless stated otherwise.

## POST /analyze

Accepts a PDF/DOCX upload, extracts segments via the Python worker, scores them with DeepSeek/OpenRouter, and returns aggregate stats.

**Request**
- Content-Type: `multipart/form-data`
- Fields:
  - `file` (required): PDF or DOCX upload
  - `threshold` (optional, float): confidence cutoff for marking text as AI. Default `0.5`.
  - `segment_tokens` (optional, int): max tokens per segment. Default `80`.

**Success Response 200**
```json
{
  "job_id": "job-abc123",
  "ai_percent": 42.7,
  "total_tokens": 1234,
  "num_segments": 87,
  "threshold": 0.5,
  "segments": [
    {
      "id": "seg-1",
      "text": "Example text.",
      "confidence": 0.87,
      "tokens": 12,
      "page": 0,
      "bbox": [10, 20, 200, 40],
      "is_ai": true
    }
  ],
  "download_token": "dl-xyz"
}
```

**Error Response**
```json
{ "error": "Message" }
```
Status codes: `400` bad request/upload errors, `405` method not allowed, `500` internal errors, `501` not implemented.

## GET /mock

Returns a static mock JSON for frontend development.

**Response 200**: contents of `backend/examples/mock_response.json`.

## GET /download?token=...

Returns an annotated PDF/DOCX once available. Currently a placeholder that returns `501 Not Implemented` with an error payload.

## Configuration
- `OPENROUTER_API_KEY`: Optional. If absent, the backend returns deterministic mock confidences for development.
- `OPENROUTER_MODEL`: Optional. Model name passed to OpenRouter when implemented (default `deepseek/deepseek-chat`).
- Upload directory: `backend/uploads/` (created automatically).

