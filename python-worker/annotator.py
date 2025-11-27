"""
Annotates a PDF with colored highlights based on confidence thresholds.

Usage:
  python annotator.py --file input.pdf --segments segments.json --output out.pdf
"""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path
from typing import Iterable, List, Tuple

try:
    import fitz  # PyMuPDF
except ImportError as exc:  # pragma: no cover
    raise SystemExit("PyMuPDF (fitz) is required for annotation.") from exc


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Annotate PDF with AI confidence highlights.")
    parser.add_argument("--file", required=True, help="Path to the source PDF.")
    parser.add_argument("--segments", required=True, help="Path to JSON array of segments with page, bbox, confidence.")
    parser.add_argument("--output", required=True, help="Path to write the annotated PDF.")
    parser.add_argument("--blue-threshold", type=float, default=0.6, help="Confidence >= this becomes blue.")
    parser.add_argument("--yellow-threshold", type=float, default=0.7, help="Confidence >= this becomes yellow.")
    parser.add_argument("--red-threshold", type=float, default=0.8, help="Confidence >= this becomes red.")
    return parser.parse_args()


def load_segments(path: Path) -> List[dict]:
    data = json.loads(path.read_text(encoding="utf-8"))
    if not isinstance(data, list):
        raise SystemExit("Segments JSON must be an array.")
    return data


def pick_color(conf: float, blue: float, yellow: float, red: float) -> Tuple[float, float, float] | None:
    if conf >= red:
        return (1.0, 0.24, 0.24)  # red
    if conf >= yellow:
        return (0.98, 0.86, 0.31)  # yellow
    if conf >= blue:
        return (0.2, 0.6, 1.0)  # blue
    return None


def iter_highlights(segments: Iterable[dict], blue: float, yellow: float, red: float):
    for seg in segments:
        page_index = seg.get("page")
        bbox = seg.get("bbox")
        conf = float(seg.get("confidence", 0))
        color = pick_color(conf, blue, yellow, red)
        if color is None:
            continue
        if page_index is None or bbox is None or len(bbox) != 4:
            continue
        yield page_index, bbox, color


def annotate_pdf(src: Path, out: Path, segments: List[dict], blue: float, yellow: float, red: float) -> None:
    doc = fitz.open(src)
    for page_index, bbox, color in iter_highlights(segments, blue, yellow, red):
        if page_index < 0 or page_index >= doc.page_count:
            continue
        page = doc.load_page(page_index)
        rect = fitz.Rect(bbox)
        annot = page.add_rect_annot(rect)
        annot.set_colors(stroke=color, fill=color)
        annot.set_opacity(0.25)
        annot.update()
    doc.save(out)


def main() -> None:
    args = parse_args()
    src = Path(args.file)
    seg_path = Path(args.segments)
    out = Path(args.output)

    if not src.exists():
        raise SystemExit(f"File not found: {src}")
    if src.suffix.lower() != ".pdf":
        raise SystemExit("Only PDF annotation is supported.")

    segments = load_segments(seg_path)
    annotate_pdf(src, out, segments, args.blue_threshold, args.yellow_threshold, args.red_threshold)

    # Return JSON so PHP can parse.
    sys.stdout.write(json.dumps({"output": str(out)}))
    sys.stdout.flush()


if __name__ == "__main__":
    main()

