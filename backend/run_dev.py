"""Convenience dev runner: `python run_dev.py` starts the API on :8001.

Equivalent to `uvicorn app.main:app --reload --port 8001` but avoids needing
to remember the module path. Use uvicorn directly in production.
"""

import uvicorn

if __name__ == "__main__":
    uvicorn.run("app.main:app", host="0.0.0.0", port=8001, reload=False)
