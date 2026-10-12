import io
import os

from fastapi import FastAPI, File, UploadFile
from fastembed import ImageEmbedding
from PIL import Image

MODEL_NAME = os.environ.get("VISION_MODEL", "Qdrant/Unicom-ViT-B-16")

model = ImageEmbedding(model_name=MODEL_NAME, cache_dir="/models")
app = FastAPI()


@app.get("/health")
def health() -> dict:
    return {"model": MODEL_NAME}


@app.post("/embed")
async def embed(file: UploadFile = File(...)) -> dict:
    image = Image.open(io.BytesIO(await file.read())).convert("RGB")
    vector = next(iter(model.embed([image])))

    return {"model": MODEL_NAME, "vector": vector.tolist()}
