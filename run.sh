#!/bin/bash

docker-compose exec phpfpm ../bin/wpsnapshots "$@"
