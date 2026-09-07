-- Runs once, on first container creation, via docker-entrypoint-initdb.d.
-- Creates the second logical database Pest uses so tests never share a database with dev.
CREATE DATABASE miautrix_test OWNER miautrix;
