extends Node2D

const SPEED := 200.0
var health: int = 100
@export var name: String = "player"

signal died(reason)

func _ready() -> void:
    print("spawned ", name)

func take_damage(amount: int) -> void:
    health -= amount
    if health <= 0:
        emit_signal("died", "hp")
    else:
        print("hp: %d" % health)
