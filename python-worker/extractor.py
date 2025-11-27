"""
Extracts text segments from PDF/DOCX files and prints JSON ready for AI analysis.

The script is intentionally simple so it can be called by the PHP backend via
`python extractor.py --file path/to/file.pdf`. It returns a JSON payload that
includes basic metadata (page numbers, bounding boxes when available, token
counts, etc.) that the analyzer endpoint can forward to DeepSeek.
"""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path
from typing import Dict, Iterable, List, Optional

try:
    import fitz  # PyMuPDF
except ImportError:  # pragma: no cover - optional dependency handling
    fitz = None

try:
    import docx  # python-docx
except ImportError:  # pragma: no cover
    docx = None

try:
    from nltk import data as nltk_data
    from nltk import sent_tokenize
except ImportError as exc:  # pragma: no cover
    raise SystemExit(
        "nltk is required. Install dependencies listed in requirements.txt."
    ) from exc


Segment = Dict[str, Optional[object]]

DEFAULT_SEGMENT_TOKENS = 80


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Extract segments from a document.")
    parser.add_argument(
        "--file",
        required=True,
        help="Path to the PDF/DOCX file to analyze.",
    )
    parser.add_argument(
        "--segment-tokens",
        type=int,
        default=DEFAULT_SEGMENT_TOKENS,
        help=f"Approximate max tokens per segment (default {DEFAULT_SEGMENT_TOKENS}).",
    )
    parser.add_argument(
        "--output",
        help="Optional path to write the JSON result. Defaults to STDOUT.",
    )
    return parser.parse_args()


def ensure_nltk_data() -> None:
    """
    Ensure required NLTK tokenizers are available. If not, guide the developer clearly.
    """
    required = [
        "tokenizers/punkt",
        "tokenizers/punkt_tab",
    ]
    missing = []
    for res in required:
        try:
            nltk_data.find(res)
        except LookupError:
            missing.append(res)

    if missing:
        raise SystemExit(
            "Missing NLTK data. Run `python -m nltk.downloader punkt punkt_tab` once."
        )


def extract_pdf_blocks(path: Path) -> List[Dict[str, object]]:
    if fitz is None:
        raise SystemExit("PyMuPDF (fitz) is required for PDF extraction.")

    doc = fitz.open(path)
    blocks: List[Dict[str, object]] = []
    for page_index in range(doc.page_count):
        page = doc.load_page(page_index)
        for block in page.get_text("blocks"):
            x0, y0, x1, y1, text, *_ = block
            text = text.strip()
            if not text:
                continue
            blocks.append(
                {
                    "text": text,
                    "page": page_index,
                    "bbox": [x0, y0, x1, y1],
                }
            )
    return blocks


def extract_docx_blocks(path: Path) -> List[Dict[str, object]]:
    if docx is None:
        raise SystemExit("python-docx is required for DOCX extraction.")

    document = docx.Document(path)
    blocks: List[Dict[str, object]] = []
    for paragraph in document.paragraphs:
        text = paragraph.text.strip()
        if not text:
            continue
        blocks.append(
            {
                "text": text,
                "page": None,
                "bbox": None,
            }
        )
    return blocks


def chunk_sentences(text: str, max_tokens: int) -> Iterable[str]:
    sentences = sent_tokenize(text)
    bucket: List[str] = []
    token_count = 0
    for sentence in sentences:
        sentence_tokens = len(sentence.split())
        if token_count + sentence_tokens > max_tokens and bucket:
            yield " ".join(bucket).strip()
            bucket = []
            token_count = 0
        bucket.append(sentence)
        token_count += sentence_tokens
    if bucket:
        yield " ".join(bucket).strip()


def blocks_to_segments(blocks: List[Dict[str, object]], max_tokens: int) -> List[Segment]:
    segments: List[Segment] = []
    seg_id = 1
    for block in blocks:
        for chunk in chunk_sentences(block["text"], max_tokens=max_tokens):
            if not chunk:
                continue
            segments.append(
                {
                    "id": f"seg-{seg_id}",
                    "text": chunk,
                    "tokens": len(chunk.split()),
                    "page": block["page"],
                    "bbox": block["bbox"],
                }
            )
            seg_id += 1
    return segments


def build_payload(segments: List[Segment], source_file: Path) -> Dict[str, object]:
    total_tokens = sum(seg["tokens"] or 0 for seg in segments)
    return {
        "source": str(source_file),
        "num_segments": len(segments),
        "total_tokens": total_tokens,
        "segments": segments,
    }


def extract_segments(path: Path, max_tokens: int) -> Dict[str, object]:
    suffix = path.suffix.lower()
    if suffix == ".pdf":
        blocks = extract_pdf_blocks(path)
    elif suffix in (".docx", ".doc"):
        blocks = extract_docx_blocks(path)
    else:
        raise SystemExit(f"Unsupported file type: {suffix}")

    segments = blocks_to_segments(blocks, max_tokens=max_tokens)
    return build_payload(segments, source_file=path)


def main() -> None:
    args = parse_args()
    ensure_nltk_data()
    source = Path(args.file)
    if not source.exists():
        raise SystemExit(f"File not found: {source}")

    payload = extract_segments(source, max_tokens=args.segment_tokens)
    output = json.dumps(payload, ensure_ascii=False, indent=2)
    if args.output:
        Path(args.output).write_text(output, encoding="utf-8")
    else:
        sys.stdout.buffer.write(output.encode("utf-8"))
        sys.stdout.buffer.flush()


if __name__ == "__main__":
    main()

