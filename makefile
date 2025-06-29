up:
	docker-compose up -d --build

down:
	docker-compose down -v

restart:
	make down
	make up
