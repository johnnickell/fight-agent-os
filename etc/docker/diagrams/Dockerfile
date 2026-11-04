FROM python:3.12-slim-bookworm@sha256:e697c81897302a4c4333bade000d98038fbb21eb064f8ee1fd42d02ebcd9d337

RUN apt-get update \
    && apt-get install -y --no-install-recommends graphviz fonts-dejavu-core \
    && rm -rf /var/lib/apt/lists/*
COPY requirements.txt /opt/diagrams/requirements.txt
RUN python -m pip install --no-cache-dir --require-hashes -r /opt/diagrams/requirements.txt
ENV PYTHONDONTWRITEBYTECODE=1 HOME=/tmp
WORKDIR /docs
ENTRYPOINT ["python", "-B"]
