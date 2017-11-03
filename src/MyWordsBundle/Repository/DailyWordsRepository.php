<?php

namespace MyWordsBundle\Repository;
use MyWordsBundle\Entity\DailyWords;

/**
 * DailyWordsRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class DailyWordsRepository extends \Doctrine\ORM\EntityRepository
{
  public function findTodaysWords($user) {
    $date = new \Datetime(date('Y-m-d') . ' 00:00:00');

    $em = $this->createQueryBuilder('w');
    $em
      ->where('w.date >= :date')
      ->setParameter('date', $date);
    $em
      ->andWhere('w.user = :user')
      ->setParameter('user', $user);
    $em
      ->andWhere('w.type = :type')
      ->setParameter('type', 'solo');

    return $em->getQuery()->getOneOrNullResult();
  }

  public function findByWWId($ww, $user) {
    $query = $this->createQueryBuilder('words');

    $query->where('words.wordWar = :ww')
          ->setParameter('ww', $ww);

    $query->andWhere('words.user = :user')
          ->setParameter('user', $user);

    $query->andWhere('words.type = :type')
          ->setParameter('type', 'word_war');

    return $query->getQuery()->getOneOrNullResult();
  }

  public function findWWParticipants($ww) {
    $date = new \Datetime(date('Y-m-d') . ' 00:00:00');

    $query = $this->createQueryBuilder('ww_words');

    $query->where('ww_words.wordWar = :ww')
          ->setParameter('ww', $ww);

    $query->orderBy('ww_words.wordCount', 'DESC');

    return $query->getQuery()->getResult();
  }

  public function findAllWWCount($user) {
    $date = new \Datetime(date('Y-m-d') . ' 00:00:00');

    $query = $this->createQueryBuilder('ww_words');

    $query->join('ww_words.wordWar', 'ww');

    $query->where('ww_words.date >= :date')
          ->setParameter('date', $date);

    $query->andWhere('ww_words.user = :user')
          ->setParameter('user', $user);

    $query->andWhere('ww_words.type = :type')
          ->setParameter('type', 'word_war');

    return $query->getQuery()->getResult();
  }

  public function findWordsByFilters($user, $filters, $page) {
    $query = $this->createQueryBuilder('words');

    $query->where('words.user = :user')
          ->setParameter('user', $user);

    $query->andWhere('words.wordCount > :wc')
          ->setParameter('wc', '0');

    if(!empty($filters)) {
      $query->andWhere('words.type NOT IN (:type)')
            ->setParameter('type', $filters);
    }

    $query->setMaxResults(DailyWords::NUM_ENTRIES);

    if($page > 1) {
      $query->setFirstResult(($page - 1) * DailyWords::NUM_ENTRIES);
    }

    return $query->getQuery()->getResult();
  }

  public function getTotalNbEntriesWithFilters($user, $filters) {
    $query = $this->createQueryBuilder('words');

    $query->select('count(words.id)');

    $query->where('words.user = :user')
          ->setParameter('user', $user);

    $query->andWhere('words.wordCount > :wc')
          ->setParameter('wc', '0');

    if(!empty($filters)) {
      $query->andWhere('words.type NOT IN (:type)')
            ->setParameter('type', $filters);
    }

    return $query->getQuery()->getSingleScalarResult();
  }
}
