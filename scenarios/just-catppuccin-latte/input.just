default: build test

build:
    cargo build --release

test: build
    cargo test --all

deploy target="staging":
    @echo "deploying to {{target}}"
    ./scripts/deploy.sh {{target}}

clean:
    rm -rf target/
