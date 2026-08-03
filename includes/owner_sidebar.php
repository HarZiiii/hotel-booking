<?php

$current_page = basename($_SERVER['PHP_SELF']);

?>


<aside class="sidebar">


<div class="brand">

<i class="fa-solid fa-hotel"></i>

<span>StayFlow Partner</span>

</div>




<ul>


<li class="<?=($current_page=='dashboard.php')?'active':''?>">

<a href="dashboard.php">

<i class="fa-solid fa-chart-line"></i>

Dashboard

</a>

</li>





<li class="<?=($current_page=='my_hotels.php')?'active':''?>">

<a href="my_hotels.php">

<i class="fa-solid fa-building-user"></i>

My Hotels

</a>

</li>





<li class="<?=($current_page=='manage_rooms.php')?'active':''?>">

<a href="manage_rooms.php">

<i class="fa-solid fa-bed"></i>

Room Inventory

</a>

</li>





<li class="<?=($current_page=='bookings.php')?'active':''?>">

<a href="bookings.php">

<i class="fa-solid fa-calendar-check"></i>

Guest Bookings

</a>

</li>





<li class="<?=($current_page=='earnings.php')?'active':''?>">

<a href="earnings.php">

<i class="fa-solid fa-wallet"></i>

Earnings & Sales

</a>

</li>





<li class="<?=($current_page=='reviews.php')?'active':''?>">

<a href="reviews.php">

<i class="fa-solid fa-star"></i>

Guest Reviews

</a>

</li>





<li class="<?=($current_page=='notifications.php')?'active':''?>">

<a href="notifications.php">

<i class="fa-solid fa-bell"></i>

Notifications

</a>

</li>





<li>

<a href="../logout.php">

<i class="fa-solid fa-right-from-bracket"></i>

Logout

</a>

</li>



</ul>


</aside>