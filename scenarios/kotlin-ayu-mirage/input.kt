package com.example.app

import kotlin.math.sqrt

data class Point(val x: Double, val y: Double) {
    fun distanceTo(other: Point): Double {
        val dx = x - other.x
        val dy = y - other.y
        return sqrt(dx * dx + dy * dy)
    }
}

fun main() {
    val points = listOf(Point(0.0, 0.0), Point(3.0, 4.0))
    val total = points.windowed(2).sumOf { (a, b) -> a.distanceTo(b) }
    val label = "total distance = $total"
    println(label)
    points.forEachIndexed { i, p -> println("#$i -> (${p.x}, ${p.y})") }
}
