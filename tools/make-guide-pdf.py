#!/usr/bin/env python3
"""Create a branded PDF guide without external dependencies."""

from pathlib import Path
import re
import textwrap


ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "docs" / "saukipay-wordpress-plugin-guide.md"
OUTPUT = ROOT / "docs" / "saukipay-wordpress-plugin-guide.pdf"

TEAL = "0.063 0.184 0.227"
GOLD = "0.965 0.659 0.102"
INK = "0.078 0.141 0.169"
MUTED = "0.380 0.455 0.490"
LIGHT = "0.955 0.980 0.984"
WHITE = "1 1 1"


def escape_pdf_text(text):
    return text.replace("\\", "\\\\").replace("(", "\\(").replace(")", "\\)")


def strip_inline_markdown(text):
    text = re.sub(r"\*\*(.*?)\*\*", r"\1", text)
    return text.replace("`", "")


def parse_blocks(markdown):
    blocks = []
    in_table = False

    for raw in markdown.splitlines():
        line = raw.strip()

        if not line:
            in_table = False
            blocks.append(("space", ""))
            continue

        if line.startswith("| ---"):
            continue

        if line.startswith("|"):
            cells = [strip_inline_markdown(cell.strip()) for cell in line.strip("|").split("|")]
            if len(cells) >= 2:
                prefix = "Option: " if not in_table else ""
                blocks.append(("body", prefix + cells[0] + " - " + cells[1]))
                in_table = True
            continue

        in_table = False

        if line.startswith("# "):
            blocks.append(("title", strip_inline_markdown(line[2:])))
        elif line.startswith("## "):
            blocks.append(("heading", strip_inline_markdown(line[3:])))
        elif line.startswith("- "):
            blocks.append(("bullet", strip_inline_markdown(line[2:])))
        elif re.match(r"^\d+\. ", line):
            blocks.append(("number", strip_inline_markdown(re.sub(r"^\d+\. ", "", line))))
        else:
            blocks.append(("body", strip_inline_markdown(line)))

    return blocks


class PdfBuilder:
    def __init__(self):
        self.objects = []
        self.pages = []
        self.current = []
        self.y = 720
        self.page_number = 0

    def add_object(self, body):
        self.objects.append(body)
        return len(self.objects)

    def begin_page(self, cover=False):
        if self.current:
            self.finish_page()

        self.page_number += 1
        self.current = []
        self.y = 720

        if cover:
            self.rect(0, 0, 612, 792, TEAL)
            self.rect(0, 0, 612, 190, LIGHT)
            self.text("Sauki", 72, 650, 36, WHITE, "F2")
            self.text("PAY", 178, 650, 36, GOLD, "F2")
            self.text("WordPress Plugin Guide", 72, 590, 15, GOLD, "F2")
            self.text("Accept Sauki Pay payments on your WordPress website.", 72, 535, 28, WHITE, "F2", max_width=22)
            self.text("Use WooCommerce checkout or a modern standalone payment form.", 72, 445, 13, "0.820 0.900 0.920", "F1", max_width=62)
            self.text("Version 1.0.0", 72, 86, 11, MUTED, "F1")
            self.y = 700
            return

        self.rect(0, 748, 612, 44, TEAL)
        self.text("Sauki", 42, 764, 14, WHITE, "F2")
        self.text("PAY", 82, 764, 14, GOLD, "F2")
        self.text("WordPress Plugin Guide", 430, 764, 9, "0.820 0.900 0.920", "F1")
        self.y = 704

    def finish_page(self):
        if not self.current:
            return

        if self.page_number > 1:
            self.text(str(self.page_number), 548, 34, 9, MUTED, "F1")

        stream = "\n".join(self.current).encode("latin-1", "replace")
        content_id = self.add_object(
            f"<< /Length {len(stream)} >>\nstream\n".encode("latin-1")
            + stream
            + b"\nendstream"
        )
        self.pages.append(content_id)
        self.current = []

    def rect(self, x, y, width, height, color):
        self.current.append(f"{color} rg {x} {y} {width} {height} re f")

    def text(self, text, x, y, size=11, color=INK, font="F1", max_width=84):
        wrapped = textwrap.wrap(text, width=max_width) or [""]
        for index, line in enumerate(wrapped):
            yy = y - (index * (size + 4))
            self.current.append(
                f"BT {color} rg /{font} {size} Tf {x} {yy} Td ({escape_pdf_text(line)}) Tj ET"
            )
        return y - (len(wrapped) * (size + 4))

    def write_block(self, kind, text):
        if kind == "title":
            return

        if kind == "space":
            self.y -= 8
            return

        if self.y < 84:
            self.begin_page()

        if kind == "heading":
            self.y -= 6
            self.text(text, 54, self.y, 17, TEAL, "F2", max_width=58)
            self.rect(54, self.y - 8, 44, 3, GOLD)
            self.y -= 30
            return

        prefix = ""
        x = 72
        width = 76

        if kind == "bullet":
            prefix = "- "
        elif kind == "number":
            prefix = "• "

        lines = textwrap.wrap(text, width=width)
        if not lines:
            self.y -= 12
            return

        for index, line in enumerate(lines):
            if self.y < 70:
                self.begin_page()
            display = prefix + line if index == 0 else "  " + line
            self.text(display, x, self.y, 10.5, INK, "F1", max_width=88)
            self.y -= 16

        self.y -= 2

    def save(self):
        self.finish_page()

        catalog_id = self.add_object(b"")
        pages_id = self.add_object(b"")
        self.objects[catalog_id - 1] = f"<< /Type /Catalog /Pages {pages_id} 0 R >>".encode("latin-1")
        font_regular_id = self.add_object(b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>")
        font_bold_id = self.add_object(b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>")

        page_object_ids = []
        for content_id in self.pages:
            page_id = self.add_object(
                (
                    "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] "
                    f"/Resources << /Font << /F1 {font_regular_id} 0 R /F2 {font_bold_id} 0 R >> >> "
                    f"/Contents {content_id} 0 R >>"
                ).encode("latin-1")
            )
            page_object_ids.append(page_id)

        kids = " ".join(f"{page_id} 0 R" for page_id in page_object_ids)
        self.objects[pages_id - 1] = f"<< /Type /Pages /Kids [{kids}] /Count {len(page_object_ids)} >>".encode("latin-1")

        data = bytearray(b"%PDF-1.4\n")
        offsets = [0]

        for idx, body in enumerate(self.objects, start=1):
            offsets.append(len(data))
            data.extend(f"{idx} 0 obj\n".encode("latin-1"))
            data.extend(body)
            data.extend(b"\nendobj\n")

        xref_offset = len(data)
        data.extend(f"xref\n0 {len(self.objects) + 1}\n".encode("latin-1"))
        data.extend(b"0000000000 65535 f \n")

        for offset in offsets[1:]:
            data.extend(f"{offset:010d} 00000 n \n".encode("latin-1"))

        data.extend(
            (
                "trailer\n"
                f"<< /Size {len(self.objects) + 1} /Root {catalog_id} 0 R >>\n"
                "startxref\n"
                f"{xref_offset}\n"
                "%%EOF\n"
            ).encode("latin-1")
        )

        OUTPUT.write_bytes(data)


def main():
    blocks = parse_blocks(SOURCE.read_text(encoding="utf-8"))
    pdf = PdfBuilder()
    pdf.begin_page(cover=True)
    pdf.begin_page()

    for kind, text in blocks:
        pdf.write_block(kind, text)

    pdf.save()
    print(OUTPUT)


if __name__ == "__main__":
    main()
