# AI Plagiarism Checker

An AI-powered plagiarism detection system that analyzes PDF and DOCX files, identifies AI-generated text, highlights suspicious sections, and provides an AI-percentage score with an annotated downloadable document. The stack combines a React frontend with a PHP + Python backend.

## Overview

The AI Plagiarism Checker lets users upload documents and then determines what percentage appears AI-generated. It splits the document into smaller text segments, sends them to the DeepSeek/OpenRouter API, and displays a detailed report that includes:

- Highlighted text preview
- AI percentage
- Confidence score for every segment
- Top AI-suspect lines
- Downloadable annotated PDF/DOCX

## How It Works

1. User uploads a PDF or DOCX via the React UI.
2. PHP backend saves the file and triggers a Python worker.
3. Python extracts text (PyMuPDF or python-docx) and splits it into segments.
4. PHP submits the segments to the DeepSeek/OpenRouter API for AI detection.
5. API returns confidence scores (0 = human, 1 = AI).
6. PHP aggregates scores, calculates an overall AI percentage, and responds with JSON.
7. React renders the dashboard (highlights, pie chart, segment list).
8. On request, Python produces a highlighted, annotated PDF/DOCX.

## Tech Stack

### Frontend
- React
- Axios
- Chart.js
- File upload UI
- Highlighted preview

### Backend
- PHP
- File handling
- Calls Python worker
- DeepSeek/OpenRouter integration
- Calculates AI percentage
- Generates download tokens

### Python Worker
- PyMuPDF (PDF extraction + bbox)
- nltk (sentence splitting)
- python-docx (DOCX extraction)
- PDF highlighter (annotator)

## API Endpoints

### `POST /analyze`
Uploads the file and returns detection results.

```json
{
  "job_id": "abc123",
  "ai_percent": 42.7,
  "total_tokens": 1234,
  "num_segments": 87,
  "segments": [
    {
      "id": "seg-1",
      "text": "Example text.",
      "confidence": 0.87,
      "tokens": 12,
      "page": 0,
      "bbox": [10, 20, 200, 40]
    }
  ],
  "download_token": "dl-xyz"
}
```

### `GET /mock`
Returns a mock JSON response for frontend development.

### `GET /download?token=...`
Returns the annotated PDF/DOCX file.

## Folder Structure

```
ai-plagiarism-checker/
|-- backend/
|   |-- public/
|   |   |-- analyze.php
|   |   |-- download.php
|   |   `-- mock.php
|   |-- src/
|   |   |-- DeepSeekClient.php
|   |   |-- PythonBridge.php
|   |   `-- Utils.php
|   |-- uploads/
|   `-- examples/
|       `-- mock_response.json
|-- python-worker/
|   |-- extractor.py
|   |-- annotator.py
|   `-- requirements.txt
|-- frontend/
|   |-- react/
|   `-- simple/
|       `-- index.html
|-- docs/
|   `-- api_spec.md
`-- README.md
```

## Local Setup (Windows)

### 1. Install Dependencies
- Node.js
- Python
- PHP (XAMPP or standalone)
- Composer
- Poppler (optional)

### 2. Python Worker
```powershell
cd python-worker
python -m venv .venv
.\\.venv\\Scripts\\Activate.ps1
pip install pymupdf nltk
python - <<PY
import nltk; nltk.download('punkt')
PY
```

### 3. Start PHP Backend
```powershell
cd backend/public
php -S 0.0.0.0:8000
```

### 4. Start React Frontend (optional)
```powershell
cd frontend/react
npm start
```

## Testing

### Backend
```bash
curl -X POST "http://localhost:8000/analyze.php" \
     -F "file=@sample.pdf" \
     -F "threshold=0.65"
```

### Mock Endpoint
`http://localhost:8000/mock.php`

## Frontend (simple static page)
- Open `frontend/simple/index.html` in your browser.
- Choose endpoint: **Mock** (no cost) or **Analyze** (uses your API key).
- Select a PDF/DOCX and click **Run Analysis**. Results show AI%, segment list, and confidences.
- Debugging: a debug panel at the bottom shows raw responses/errors. Backend runs at `http://127.0.0.1:8000` (start from `backend/public` with `php -S 127.0.0.1:8000`).

### Frontend
- Open `frontend/simple/index.html`, or
- Run React at `http://localhost:3000`

## Future Improvements
- OCR for scanned documents
- Saving reports to a database
- Multiple detectors
- Per-segment explanations

## Summary

This project provides a complete pipeline for detecting AI-generated text in PDF/DOCX documents. With clear architecture, modular components, and a simple UI, it is ready for academic submission or further expansion.
