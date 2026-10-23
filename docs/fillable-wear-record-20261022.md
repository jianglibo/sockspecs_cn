# Fillable wear record — 2026-10-22

Converted the existing single-page A4 printable record into an unencrypted PDF
AcroForm. It retains eight usage rows, order/account and model information,
initial condition, durability observations and the summary. There are 86 text
fields and one mutually exclusive continue/stop radio group (88 widgets).

- Source: tools/pdf/build-wear-record.py (reportlab and pypdf).
- Published asset: theme/assets/downloads/sockspecs-wear-record.pdf.
- Full Unicode TrueType font embedded for mixed Chinese/Latin input; the larger
  file (8,135,906 bytes) is intentional. No external font request or JavaScript.
- Download URL cache key updated to 20261022-3. The page and submission instructions
  now accept a completed PDF as well as photos/scans of handwritten forms.
- Code release: 20261022T111851575772Z, previous 20261022T042530962695Z.
- Page publication: publish-wear-feedback.php apply; source-hash guard extended to
  accept the previously reviewed copy. Site visibility preserved.

Validation: 87 canonical fields and 88 widgets checked; Chinese/Latin sample values
filled across all rows, saved and reopened. Widget values agree with canonical
fields, and appearance streams are present. Both blank and completed copies were
rendered with Poppler and visually inspected; summary-field overlaps were corrected.
The bundled pypdf appearance writer required a QA-only workaround removing its
internal integer CMap sentinel from the character map. The production PDF retains
its standard Unicode CMap and contains no workaround code.

The in-app browser did not render its embedded PDF viewer, so browser editing was
not claimed as verified. Use a PDF reader supporting AcroForm filling and saving.
The website download was fetched over verified HTTPS and its SHA256 matched the
local blank artifact. No sample-filled PDF was deployed. Existing deployment PHP,
coding standards, domestic boundary and JavaScript checks passed.

SHA256: ca18a6305b9036ece111635a18efd39ec6b3727361e997ec67a2b6da046484bb
