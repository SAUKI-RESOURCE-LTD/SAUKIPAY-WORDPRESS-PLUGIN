#!/usr/bin/env python3
"""Generate the Sauki Pay guide PDF from the styled HTML file."""

from pathlib import Path
import shutil
import subprocess
import sys


ROOT = Path(__file__).resolve().parents[1]
HTML = ROOT / "docs" / "index.html"
PDF = ROOT / "docs" / "saukipay-wordpress-plugin-guide.pdf"
MAC_CHROME = Path("/Applications/Google Chrome.app/Contents/MacOS/Google Chrome")


def chrome_path():
    for name in ("google-chrome", "chromium", "chromium-browser"):
        found = shutil.which(name)
        if found:
            return found

    if MAC_CHROME.exists():
        return str(MAC_CHROME)

    return None


def main():
    chrome = chrome_path()
    if not chrome:
        print("Google Chrome or Chromium is required to generate the PDF.", file=sys.stderr)
        return 1

    command = [
        chrome,
        "--headless",
        "--disable-gpu",
        "--no-first-run",
        f"--print-to-pdf={PDF}",
        HTML.resolve().as_uri(),
    ]

    subprocess.run(command, check=True)
    print(PDF)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
