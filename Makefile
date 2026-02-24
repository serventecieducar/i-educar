.PHONY: test test-setup test-all install prepare-tests help

# Comandos de teste
test: ## Executa todos os testes
	composer test

test-setup: ## Executa apenas os testes do setup (comando ieducar:setup e seeders)
	composer test:setup

test-all: prepare-tests test ## Prepara ambiente e executa todos os testes

# Preparação
install: ## Instala dependências
	composer install

prepare-tests: ## Prepara ambiente para testes (cria testes legados)
	php artisan legacy:create:tests

# Setup do i-Educar
setup: ## Executa o comando de setup do i-Educar
	php artisan ieducar:setup

# Utilidades
help: ## Exibe esta ajuda
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'
