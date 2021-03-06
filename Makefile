release debug: build

release: BUILD_DIR=./build-release/
release: BUILD_TYPE=Release

debug: BUILD_DIR=./build-debug/
debug: BUILD_TYPE=Debug

build:
	rm -fr $(BUILD_DIR)
	mkdir -p $(BUILD_DIR)
	cd $(BUILD_DIR) && cmake -DCMAKE_BUILD_TYPE=$(BUILD_TYPE) ../src/ && make

install:
	cd ./build-release/ && make install

uninstall:
	cd ./build-release/ && xargs rm < install_manifest.txt

clean:
	rm -fr ./build-release/ ./build-debug/

.PHONY: build build-release build-debug install uninstall clean
