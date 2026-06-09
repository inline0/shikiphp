.section .text
.globl _start

_start:
    li      a0, 10
    jal     ra, factorial
    mv      s0, a0
    j       done

factorial:
    li      t0, 1
    ble     a0, t0, base
    addi    sp, sp, -8
    sw      ra, 0(sp)
    ret

base:
    li      a0, 1
    ret

done:
    li      a7, 93
    ecall
