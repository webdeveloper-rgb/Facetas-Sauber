#!/usr/bin/env python3
"""Build a deterministic, uploadable module ZIP without preview/dev files."""
import argparse
from pathlib import Path
from zipfile import ZipFile, ZipInfo, ZIP_DEFLATED

root = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('--output', type=Path, default=root / 'dist' / 'facetasdrawer.zip')
args = parser.parse_args()
args.output.parent.mkdir(parents=True, exist_ok=True)
with ZipFile(args.output, 'w', compression=ZIP_DEFLATED) as archive:
    for path in sorted((root / 'facetasdrawer').rglob('*')):
        if path.is_file():
            entry = ZipInfo(path.relative_to(root).as_posix(), date_time=(2026, 1, 1, 0, 0, 0))
            entry.compress_type = ZIP_DEFLATED
            entry.external_attr = 0o100644 << 16
            archive.writestr(entry, path.read_bytes())
with ZipFile(args.output) as archive:
    assert archive.testzip() is None
    assert 'facetasdrawer/facetasdrawer.php' in archive.namelist()
    assert all(name.startswith('facetasdrawer/') for name in archive.namelist())
print(f'Module archive built and verified: {args.output}')
