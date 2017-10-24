<?php

namespace MyStatsBundle\Repository;

/**
 * MyDailyStatsRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class MyDailyStatsRepository extends \Doctrine\ORM\EntityRepository
{
  public function findTodaysStats($user) {
    $date = new \Datetime(date('Y-m-d') . ' 00:00:00');

    $em = $this->createQueryBuilder('w');
    $em
      ->where('w.date >= :date')
      ->setParameter('date', $date);
    $em
      ->andWhere('w.user = :user')
      ->setParameter('user', $user);

    return $em->getQuery()->getOneOrNullResult();
  }

  public function findThisMonthsStats($user, $date = null) {
    if(null === $date) {
      $starting_date = new \Datetime(date('Y-m') . '-01 00:00:00');
      $ending_date = new \Datetime(date('Y-m-d') . ' 23:59:59');
    }
    else {
      $starting_date = new \Datetime($date->format('Y-m') . '-01 00:00:00');
      $ending_date = new \Datetime($date->format('Y-m') . ' 23:59:59');
    }

    $em = $this->createQueryBuilder('w');
    $em
      ->where('w.date >= :starting_date')
      ->setParameter('starting_date', $starting_date);
    $em
      ->andWhere('w.date <= :ending_date')
      ->setParameter('ending_date', $ending_date);
    $em
      ->andWhere('w.user = :user')
      ->setParameter('user', $user);

    return $em->getQuery()->getResult();
  }
}
