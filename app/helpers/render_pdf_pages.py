import sys
import json
import base64
import io

def render_pdf(pdf_path):
    try:
        import pypdfium2 as pdfium
        doc = pdfium.PdfDocument(pdf_path)
        images = []
        for i in range(len(doc)):
            page = doc[i]
            # Scale 2 gives ~144-150 DPI, crisp for print & Word while keeping byte size fast
            pil_image = page.render(scale=2).to_pil()
            buf = io.BytesIO()
            pil_image.save(buf, format="JPEG", quality=85)
            images.append(base64.b64encode(buf.getvalue()).decode('utf-8'))
        print(json.dumps({"success": True, "pages": images}))
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}))

if __name__ == "__main__":
    if len(sys.argv) > 1:
        render_pdf(sys.argv[1])
    else:
        print(json.dumps({"success": False, "error": "No file path provided"}))
