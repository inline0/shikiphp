# Simple C project Makefile
CC      := gcc
CFLAGS  := -Wall -Wextra -O2
SRC     := $(wildcard src/*.c)
OBJ     := $(SRC:.c=.o)
TARGET  := app

.PHONY: all clean test

all: $(TARGET)

$(TARGET): $(OBJ)
	$(CC) $(CFLAGS) -o $@ $^

%.o: %.c
	$(CC) $(CFLAGS) -c $< -o $@

test: all
	./$(TARGET) --selftest

clean:
	rm -f $(OBJ) $(TARGET)
