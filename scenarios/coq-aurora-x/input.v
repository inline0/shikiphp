Inductive nat : Type :=
  | O : nat
  | S : nat -> nat.

Fixpoint plus (n m : nat) : nat :=
  match n with
  | O => m
  | S p => S (plus p m)
  end.

Theorem plus_O_n : forall n : nat, plus O n = n.
Proof.
  intros n. simpl. reflexivity.
Qed.
