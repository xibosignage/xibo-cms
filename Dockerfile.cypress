FROM cypress/base:8

WORKDIR /app

COPY cypress.json ./
COPY cypress ./cypress

RUN npm install --save-dev --slient cypress

RUN $(npm bin)/cypress verify