# React Frontend (Vite)

Modern UI for the AI Plagiarism Checker. Talks to the PHP backend at `http://127.0.0.1:8000` by default and supports both `mock.php` and `analyze.php`.

## Setup

```bash
cd frontend/react
npm install
```

## Run

```bash
# dev server
npm run dev
# build
npm run build
```

Open the dev server URL shown in the terminal (defaults to http://127.0.0.1:5173). In the UI you can change the API base, choose mock vs analyze, set segment tokens, upload a PDF/DOCX, and inspect raw responses in the debug panel.
