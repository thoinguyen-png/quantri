import contextlib
import io
import json
import os
import re
import sys
import traceback
import unicodedata


def fix_windows_path_environment():
    current_path = (
        os.environ.get("path")
        or os.environ.get("Path")
        or os.environ.get("PATH")
        or ""
    )

    python_exe_dir = os.path.dirname(sys.executable)
    venv_dir = os.path.dirname(python_exe_dir)

    fallback_paths = [
        python_exe_dir,
        venv_dir,
        r"C:\Windows\System32",
        r"C:\Windows",
        r"C:\Windows\System32\Wbem",
        r"C:\Windows\System32\WindowsPowerShell\v1.0",
    ]

    merged_parts = []

    for item in fallback_paths:
        if item and item not in merged_parts:
            merged_parts.append(item)

    for item in current_path.split(os.pathsep):
        item = item.strip()
        if item and item not in merged_parts:
            merged_parts.append(item)

    fixed_path = os.pathsep.join(merged_parts)
    os.environ["path"] = fixed_path
    os.environ["Path"] = fixed_path
    os.environ["PATH"] = fixed_path


fix_windows_path_environment()

SCRIPT_VERSION = "paddle_ocr_vneid_dual_lang_20260712"

os.environ.setdefault("FLAGS_use_mkldnn", "0")
os.environ.setdefault("FLAGS_enable_pir_api", "0")
os.environ.setdefault("KMP_DUPLICATE_LIB_OK", "TRUE")


def emit(payload):
    payload["script_version"] = SCRIPT_VERSION
    sys.stdout.write(json.dumps(payload, ensure_ascii=False))
    sys.stdout.flush()


def preview(value, limit=1000):
    try:
        text = repr(value)
    except Exception:
        text = "<unrepresentable>"

    return text[:limit]


def extract_text_items(result):
    items = []

    def add(value, confidence=None):
        if not isinstance(value, str):
            return

        text = " ".join(value.strip().split())

        if not text:
            return

        try:
            score = float(confidence) if confidence is not None else None
        except Exception:
            score = None

        items.append({
            "text": text,
            "confidence": score,
        })

    def walk(value):
        if value is None:
            return

        if isinstance(value, str):
            add(value)
            return

        if isinstance(value, dict):
            rec_texts = value.get("rec_texts")
            rec_scores = value.get("rec_scores") or value.get("scores") or value.get("rec_score")

            if isinstance(rec_texts, list):
                for index, text in enumerate(rec_texts):
                    score = rec_scores[index] if isinstance(rec_scores, list) and index < len(rec_scores) else None
                    add(text, score)

            for key in ("texts", "text", "ParsedText"):
                candidate = value.get(key)

                if isinstance(candidate, list):
                    for item in candidate:
                        add(item)
                else:
                    add(candidate)

            for key in ("data", "res", "result", "results", "ocr_result"):
                if key in value:
                    walk(value.get(key))

            return

        for attr in ("rec_texts", "texts", "data", "res", "result"):
            try:
                if hasattr(value, attr):
                    walk(getattr(value, attr))
            except Exception:
                pass

        if hasattr(value, "json"):
            try:
                payload = value.json
                walk(payload() if callable(payload) else payload)
                return
            except Exception:
                pass

        if hasattr(value, "to_dict"):
            try:
                walk(value.to_dict())
                return
            except Exception:
                pass

        if isinstance(value, (list, tuple)):
            # PaddleOCR 2.x common item: [box, (text, score)]
            if len(value) >= 2:
                second = value[1]

                if isinstance(second, str):
                    add(second)
                    return

                if isinstance(second, (list, tuple)):
                    text = None
                    score = None

                    for item in second:
                        if isinstance(item, str) and text is None:
                            text = item
                        elif isinstance(item, (int, float)) and score is None:
                            score = item

                    if text is not None:
                        add(text, score)
                        return

            for item in value:
                walk(item)

    walk(result)

    clean = []
    seen = set()

    for item in items:
        line = item["text"]

        if line and line not in seen:
            clean.append(item)
            seen.add(line)

    return clean


def extract_lines(result):
    return [item["text"] for item in extract_text_items(result)]


def configure_paddle_runtime():
    try:
        import paddle

        try:
            paddle.set_flags({"FLAGS_use_mkldnn": False})
        except Exception:
            pass
    except Exception:
        pass


def build_ocr(lang="vi"):
    noise_out = io.StringIO()
    noise_err = io.StringIO()

    with contextlib.redirect_stdout(noise_out), contextlib.redirect_stderr(noise_err):
        configure_paddle_runtime()

        from paddleocr import PaddleOCR

        constructors = [
            {
                "use_angle_cls": True,
                "lang": lang,
                "use_gpu": False,
                "show_log": False,
            },
            {
                "lang": lang,
                "use_gpu": False,
                "show_log": False,
            },
            {
                "use_angle_cls": True,
                "lang": lang,
            },
            {
                "lang": lang,
            },
        ]

        last_error = None

        for kwargs in constructors:
            try:
                return PaddleOCR(**kwargs)
            except Exception as exc:
                last_error = exc

        raise last_error


def build_ocr_models():
    return {
        "vi": build_ocr("vi"),
        "en": build_ocr("en"),
    }


def read_image_array(image_path):
    try:
        import cv2
        import numpy as np

        image_bytes = np.fromfile(image_path, dtype=np.uint8)
        image = cv2.imdecode(image_bytes, cv2.IMREAD_COLOR)

        if image is None:
            return None, {
                "ok": False,
                "error_type": "ImageReadError",
                "message": "cv2.imdecode returned None",
            }

        return image, None

    except Exception as exc:
        return None, {
            "ok": False,
            "error_type": type(exc).__name__,
            "message": str(exc),
            "traceback": traceback.format_exc(limit=3),
        }


def attempt_ocr(api_name, fn):
    noise_out = io.StringIO()
    noise_err = io.StringIO()

    try:
        with contextlib.redirect_stdout(noise_out), contextlib.redirect_stderr(noise_err):
            result = fn()

        text_items = extract_text_items(result)
        lines = [item["text"] for item in text_items]

        return {
            "api": api_name,
            "ok": bool(lines),
            "lines": lines,
            "text_items": text_items,
            "lines_count": len(lines),
            "result_type": type(result).__name__,
            "result_preview": preview(result),
        }

    except Exception as exc:
        return {
            "api": api_name,
            "ok": False,
            "lines": [],
            "text_items": [],
            "error_type": type(exc).__name__,
            "message": str(exc),
            "traceback": traceback.format_exc(limit=5),
        }


def normalize_for_score(text):
    value = unicodedata.normalize("NFKD", text)
    value = "".join(char for char in value if not unicodedata.combining(char))
    value = value.lower().replace("đ", "d").replace("Đ", "d")
    value = re.sub(r"[^a-z0-9]+", " ", value)

    return re.sub(r"\s+", " ", value).strip()


def score_ocr_result(lines, text_items=None):
    raw_text = "\n".join(lines)
    normalized = normalize_for_score(raw_text)
    score = 0

    if re.search(r"(?<!\d)\d{12}(?!\d)", raw_text):
        score += 35
    else:
        score -= 25

    checks = [
        (r"(ho\s*va\s*ten|full\s*name)", 14),
        (r"(ngay\s*sinh|date\s*of\s*birth)", 12),
        (r"(gioi\s*tinh|sex)", 10),
        (r"(que\s*quan|place\s*of\s*origin)", 12),
        (r"(noi\s*thuong\s*tru|place\s*of\s*residence)", 12),
        (r"(ngay\s*cap|date\s*of\s*issue)", 14),
    ]

    for pattern, points in checks:
        if re.search(pattern, normalized):
            score += points
        else:
            score -= max(4, points // 2)

    dates = re.findall(r"\b\d{1,2}\s*[\/\-.]\s*\d{1,2}\s*[\/\-.]\s*\d{4}\b", raw_text)
    score += min(len(dates), 3) * 6

    if re.search(r"(?:^|\s)(nam|nu|male|female)(?:\s|$)", normalized):
        score += 8

    good_confidence_count = 0

    for item in text_items or []:
        confidence = item.get("confidence")

        if isinstance(confidence, (int, float)) and confidence >= 0.80:
            good_confidence_count += 1

    score += min(good_confidence_count, 12) * 2

    if "�" in raw_text:
        score -= 30

    mojibake_count = sum(raw_text.count(token) for token in ("Ã", "Â", "Ä", "¾"))
    score -= mojibake_count * 8

    suspicious_count = len(re.findall(r"[^\w\sÀ-ỹĐđ.,:;\/\-\(\)'%+]", raw_text, flags=re.UNICODE))
    score -= min(suspicious_count, 20)

    for bad in ("con", "don", "cõn", "dõn"):
        if bad in normalized:
            score -= 3

    return score


def success_payload_from_attempts(attempts):
    for attempt in attempts:
        if attempt.get("ok"):
            return {
                "ok": True,
                "raw_text": "\n".join(attempt["lines"]),
                "lines": attempt["lines"],
                "text_items": attempt.get("text_items", []),
                "message": "",
                "api": attempt["api"],
                "debug": {"attempts": attempts},
            }

    return None


def run_single_ocr(ocr, image_path):
    attempts = []
    image_array, image_error = read_image_array(image_path)

    if image_error:
        attempts.append({
            "api": "read_image_array",
            "ok": False,
            **image_error,
        })

    if image_array is not None:
        for api_name, fn in (
            ("ocr_cls_image_array", lambda: ocr.ocr(image_array, cls=True)),
            ("ocr_image_array", lambda: ocr.ocr(image_array)),
        ):
            attempt = attempt_ocr(api_name, fn)
            attempts.append(attempt)

            payload = success_payload_from_attempts([attempt])

            if payload:
                payload["debug"] = {"attempts": attempts}
                return payload

    # Path fallback only; never call predict().
    for api_name, fn in (
        ("ocr_cls_path", lambda: ocr.ocr(image_path, cls=True)),
        ("ocr_path", lambda: ocr.ocr(image_path)),
    ):
        attempt = attempt_ocr(api_name, fn)
        attempts.append(attempt)

        payload = success_payload_from_attempts([attempt])

        if payload:
            payload["debug"] = {"attempts": attempts}
            return payload

    return {
        "ok": False,
        "raw_text": "",
        "lines": [],
        "message": "No text recognized.",
        "error_type": "NoTextRecognized",
        "debug": {
            "image_path": image_path,
            "image_exists": os.path.isfile(image_path),
            "image_size": os.path.getsize(image_path) if os.path.isfile(image_path) else 0,
            "attempts": attempts,
        },
    }


def run_ocr(ocr, image_path):
    if not isinstance(ocr, dict):
        payload = run_single_ocr(ocr, image_path)
        payload.pop("text_items", None)
        return payload

    results = {}
    scores = {}

    for lang in ("vi", "en"):
        model = ocr.get(lang)

        if model is None:
            continue

        payload = run_single_ocr(model, image_path)
        payload["selected_lang"] = lang
        results[lang] = payload

        scores[lang] = score_ocr_result(payload.get("lines", []), payload.get("text_items", [])) if payload.get("ok") else -999

    if not results:
        return {
            "ok": False,
            "raw_text": "",
            "lines": [],
            "message": "No OCR model available.",
            "error_type": "NoModel",
            "debug": {
                "vi_score": scores.get("vi"),
                "en_score": scores.get("en"),
                "selected_lang": "",
            },
        }

    vi_score = scores.get("vi", -999)
    en_score = scores.get("en", -999)
    selected_lang = "vi" if vi_score >= en_score - 5 else "en"
    selected = results.get(selected_lang) or next(iter(results.values()))

    selected.pop("text_items", None)
    selected["selected_lang"] = selected_lang
    selected["api"] = f"{selected.get('api', 'ocr')}_{selected_lang}"

    debug = selected.get("debug") if isinstance(selected.get("debug"), dict) else {}
    debug.update({
        "vi_score": vi_score,
        "en_score": en_score,
        "selected_lang": selected_lang,
    })
    selected["debug"] = debug

    return selected


def ocr_image(image_path, ocr_instance=None):
    models = ocr_instance if ocr_instance is not None else build_ocr_models()
    return run_ocr(models, image_path)


def main():
    if len(sys.argv) < 2:
        emit({
            "ok": False,
            "raw_text": "",
            "lines": [],
            "message": "Missing image path argument",
            "error_type": "MissingArgument",
        })
        return 1

    image_path = sys.argv[1]

    if not os.path.isfile(image_path):
        emit({
            "ok": False,
            "raw_text": "",
            "lines": [],
            "message": "Image path not found: " + image_path,
            "error_type": "FileNotFound",
        })
        return 1

    try:
        payload = ocr_image(image_path)
        emit(payload)
        return 0 if payload.get("ok") else 1

    except ModuleNotFoundError as exc:
        emit({
            "ok": False,
            "raw_text": "",
            "lines": [],
            "message": "PaddleOCR is not installed.",
            "error_type": type(exc).__name__,
            "traceback": traceback.format_exc(limit=5),
        })
        return 1

    except Exception as exc:
        emit({
            "ok": False,
            "raw_text": "",
            "lines": [],
            "message": str(exc),
            "error_type": type(exc).__name__,
            "traceback": traceback.format_exc(limit=8),
        })
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
