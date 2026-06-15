# Realtime Calendar WebSocket Server

This service broadcasts calendar changes to all open backend calendar pages.

## 1) Install

From this folder:

- npm install

## 2) Run

- set REALTIME_SECRET=change-this-secret
- node server.js

Optional:

- set REALTIME_PORT=8081

## 3) Configure Easy!Appointments

Set the same secret in config.php:

- Config::REALTIME_WS_SECRET

Default values used by this setup:

- WebSocket URL: ws://127.0.0.1:8081
- Publish URL: http://127.0.0.1:8081/publish

## 4) Health check

- GET http://127.0.0.1:8081/health

## Notes

- Keep this service running with a process manager in production (PM2, NSSM, systemd, etc.).
- Expose this service only internally.
