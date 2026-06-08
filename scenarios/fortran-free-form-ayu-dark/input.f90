module geometry
  implicit none
  real, parameter :: pi = 3.14159265
contains
  pure function area(r) result(a)
    real, intent(in) :: r
    real :: a
    a = pi * r**2
  end function area
end module geometry

program main
  use geometry
  integer :: i
  do i = 1, 3
    print *, "area", area(real(i))
  end do
end program main
