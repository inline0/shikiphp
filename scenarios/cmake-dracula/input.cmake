cmake_minimum_required(VERSION 3.20)
project(Demo VERSION 1.2.0 LANGUAGES CXX)

set(CMAKE_CXX_STANDARD 20)
option(BUILD_TESTS "Build the test suite" ON)

# Collect all sources
file(GLOB SOURCES "src/*.cpp")

add_library(core STATIC ${SOURCES})
target_include_directories(core PUBLIC include)

if(BUILD_TESTS)
    enable_testing()
    add_executable(tests test/main.cpp)
    target_link_libraries(tests PRIVATE core)
    add_test(NAME unit COMMAND tests)
endif()

foreach(flag IN ITEMS -Wall -Wextra)
    message(STATUS "Adding flag: ${flag}")
endforeach()
