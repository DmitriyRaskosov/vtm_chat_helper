FROM node:22-bookworm-slim

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm install --include=optional

COPY . .

EXPOSE 5173

CMD ["npm", "run", "dev", "--", "--host"]
