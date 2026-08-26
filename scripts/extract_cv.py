#!/usr/bin/env python3
"""M17-3 — Extract plain text from a CV file (PDF/DOCX/TXT).

Usage: extract_cv.py <path>
Prints extracted text to stdout. Exit 0 on success, non-zero on failure.
Keeps output bounded so a huge CV cannot blow up the DB / embedding payload.
"""
import sys
import os

MAX_CHARS = 20000


def extract_pdf(path: str) -> str:
    import fitz  # PyMuPDF
    doc = fitz.open(path)
    parts = []
    for page in doc:
        parts.append(page.get_text())
    doc.close()
    return "\n".join(parts)


def extract_docx(path: str) -> str:
    # python-docx if available, else bail gracefully.
    try:
        import docx
    except ImportError:
        return ""
    d = docx.Document(path)
    return "\n".join(p.text for p in d.paragraphs)


def main() -> int:
    if len(sys.argv) < 2:
        print("usage: extract_cv.py <path>", file=sys.stderr)
        return 2

    path = sys.argv[1]
    if not os.path.isfile(path):
        print(f"file not found: {path}", file=sys.stderr)
        return 3

    ext = os.path.splitext(path)[1].lower()
    try:
        if ext == ".pdf":
            text = extract_pdf(path)
        elif ext in (".docx",):
            text = extract_docx(path)
        elif ext in (".txt",):
            with open(path, "r", errors="ignore") as f:
                text = f.read()
        else:
            # Last resort: try PDF parser anyway.
            text = extract_pdf(path)
    except Exception as e:  # noqa: BLE001
        print(f"extract error: {e}", file=sys.stderr)
        return 4

    # Normalise whitespace, bound length.
    text = " ".join(text.split())
    if len(text) > MAX_CHARS:
        text = text[:MAX_CHARS]

    sys.stdout.write(text)
    return 0


if __name__ == "__main__":
    sys.exit(main())
