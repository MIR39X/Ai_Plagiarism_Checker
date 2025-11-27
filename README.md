# AI Plagiarism Checker

An AI-powered plagiarism detection system that analyzes PDF/DOCX files, scores per-segment AI likelihood, and produces a downloadable, highlighted PDF. Built as my Digital Forensics course project to practice AI-generated text detection and evidence-style reporting.

## Why I Built It
- Digital Forensics coursework: needed a practical tool to flag AI-generated text and present findings clearly.
- PHP for the backend because it’s the language I’m most comfortable with (previous database project) and it let me ship quickly.
- Python for text extraction/annotation, React for a clean, modern UI.

## What It Does
- Upload a PDF/DOCX, get AI percentage, per-segment confidences, and a segment list.
- Mock mode (free) or live mode via OpenRouter (DeepSeek/GPT models).
- Download annotated PDF with colored highlights:
  - Blue: confidence > 0.6
  - Yellow: confidence > 0.7
  - Red: confidence > 0.8

## How It Works
1. React UI uploads the document.
2. PHP backend stores the file and calls the Python extractor.
3. Python extracts text (PyMuPDF or python-docx) and chunks into segments with pages/bboxes.
4. PHP sends segments to OpenRouter (or uses deterministic mock if no API key).
5. Scores return (0 = human, 1 = AI); PHP aggregates AI%.
6. React renders summary, segments, debug; download token is issued.
7. Download endpoint runs Python annotator to highlight AI segments on the PDF and serves it.

## Tech Stack (and why)
- Frontend: React + Vite (fast dev, polished UI), simple fetch calls.
- Backend: PHP (familiar, quick to build), file handling, OpenRouter integration.
- Python worker: PyMuPDF, nltk, python-docx for extraction and PDF highlighting.

## API Endpoints
- `POST /analyze.php` — upload + score; returns JSON with `download_token`.
- `GET /mock.php` — static mock JSON for development.
- `GET /download.php?token=...` — annotated PDF (PDF uploads only).

## Folder Structure
```
backend/
  public/ (analyze.php, download.php, mock.php)
  src/ (DeepSeekClient.php, PythonBridge.php, Utils.php)
  uploads/ (jobs + annotated outputs)
  examples/mock_response.json
python-worker/
  extractor.py
  annotator.py
  requirements.txt
frontend/
  simple/index.html      (legacy dev page)
  react/                 (Vite + React polished UI)
docs/
  api_spec.md
```

## Local Setup (Windows)
1) Install: Node.js, Python, PHP (XAMPP or standalone), optional Composer/Poppler.  
2) Python worker:
```powershell
cd python-worker
python -m venv .venv
.\.venv\Scripts\activate
pip install -r requirements.txt
python -m nltk.downloader punkt punkt_tab
```
3) Backend:
```powershell
cd backend/public
php -S 127.0.0.1:8000
```
4) React UI:
```powershell
cd frontend/react
npm install
npm run dev
```

## Quick Test
From `backend/public`:
```bash
curl -X POST "http://127.0.0.1:8000/analyze.php" -F "file=@../../sample.pdf"
```
Mock: `http://127.0.0.1:8000/mock.php`  
Download: `http://127.0.0.1:8000/download.php?token=...` (PDF only)

## Future Ideas
- DOCX annotation
- OCR for scanned docs
- Auth/rate limits and storage
- Multiple detectors and explanations

Mir signing off
