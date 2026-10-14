from pathlib import Path

from research.merge_test import merge_documents


def main() -> None:
    project_root = Path(__file__).resolve().parents[2]
    doc1_path = project_root / "docx" / "PartA.docx"
    doc2_path = project_root / "docx" / "PartB.docx"
    output_path = project_root / "docx" / "combined.docx"

    print("Hello from research!")
    merge_documents(str(doc1_path), str(doc2_path), str(output_path))
    print(f"Documents merged successfully: {output_path}")
