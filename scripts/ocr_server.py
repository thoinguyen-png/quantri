import os
import tempfile

from flask import Flask, jsonify, request

from paddle_ocr_vneid import build_ocr_models, run_ocr


app = Flask(__name__)
ocr_models = None


def get_ocr_models():
    global ocr_models

    if ocr_models is None:
        ocr_models = build_ocr_models()

    return ocr_models


@app.get("/health")
def health():
    return jsonify({
        "ok": True,
        "ready": ocr_models is not None,
        "loaded_langs": sorted((ocr_models or {}).keys()),
    })


@app.post("/ocr")
def ocr():
    uploaded = request.files.get("image")

    if uploaded is None or uploaded.filename == "":
        return jsonify({
            "ok": False,
            "raw_text": "",
            "lines": [],
            "message": "Missing image file.",
            "error_type": "MissingImage",
        }), 400

    suffix = os.path.splitext(uploaded.filename or "")[1].lower()

    if suffix not in (".jpg", ".jpeg", ".png", ".webp"):
        suffix = ".jpg"

    temp_path = None

    try:
        with tempfile.NamedTemporaryFile(delete=False, suffix=suffix) as temp_file:
            uploaded.save(temp_file)
            temp_path = temp_file.name

        if not os.path.isfile(temp_path) or os.path.getsize(temp_path) <= 0:
            return jsonify({
                "ok": False,
                "raw_text": "",
                "lines": [],
                "message": "Uploaded image is empty.",
                "error_type": "EmptyImage",
            }), 400

        payload = run_ocr(get_ocr_models(), temp_path)

        return jsonify(payload), 200 if payload.get("ok") else 422

    except Exception as exc:
        app.logger.exception("OCR request failed")

        return jsonify({
            "ok": False,
            "raw_text": "",
            "lines": [],
            "message": "OCR failed.",
            "error_type": type(exc).__name__,
        }), 500

    finally:
        if temp_path and os.path.isfile(temp_path):
            try:
                os.remove(temp_path)
            except OSError:
                app.logger.warning("Could not delete OCR temp file: %s", temp_path)


if __name__ == "__main__":
    get_ocr_models()
    app.run(host="127.0.0.1", port=8100, threaded=False)
