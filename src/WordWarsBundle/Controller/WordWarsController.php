<?php

namespace WordWarsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use WordWarsBundle\Entity\WordWar;
use MyWordsBundle\Entity\DailyWords;
use MyStatsBundle\Entity\MyDailyStats;
use WordWarsBundle\Form\Type\WordWarType;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class WordWarsController extends Controller
{
  public function newAction(Request $request)
  {
    $user = $this->getUser();
    $word_war = new WordWar($user);

    $form = $this->get('form.factory')->create(WordWarType::class, $word_war);

    if($request->isMethod('POST')) {
      $form->handleRequest($request);

      // Sauvegarde de la nouvelle WW
      if($form->isValid()) {
        // Formattage de la date pour qu'à l'heure sélectionnée s'ajoute la date du jour
        $word_war->setStart(new \DateTime($word_war->getStart()->format('H:i:s')));
        $word_war->setEnd(new \DateTime($word_war->getEnd()->format('H:i:s')));

        // Persistage de la word war nouvellement créée
        $manager = $this->getDoctrine()->getManager();
        $manager->persist($word_war);
        $manager->flush();

        // On fournit l'url de ladite WW pour la partager
        $request
          ->getSession()
          ->getFlashBag()
          ->add(
            'status',
            'Votre Word War a été créée, vous pouvez partager le lien suivant pour inviter vos amis : ' . $this->generateUrl('word_wars_in_progress', array('id' => $word_war->getId()), UrlGeneratorInterface::ABSOLUTE_URL) . '.');

        // Et on redirige vers l'espace de WW
        return $this->redirectToRoute('word_wars_in_progress', array('id' => $word_war->getId()));
      }
    }

    return $this->render('WordWarsBundle:WordWars:new.html.twig', array('form' => $form->createView()));
  }

  public function inProgressAction($id) {
    // Récupération du user en cours
    $user = $this->getUser();
    $manager = $this->getDoctrine()->getManager();

    // Récupération de la WW
    $ww_repo = $manager->getRepository('WordWarsBundle:WordWar');
    $word_war = $ww_repo->find($id);

    // On vérifie avant tout qu'elle est toujours en cours
    $date = new \DateTime();

    if($word_war->getEnd() < $date) {
      return $this->redirectToRoute('word_war_ended', array('id' => $id));
    }

    // Récupération du repo daily_words
    $repo = $manager->getRepository('MyWordsBundle:DailyWords');
    $my_word_war = $repo->findByWWId($word_war, $user);

    $saved_words = '';
    $word_count = 0;

    // Si on a déjà des mots pour la journée, on les affiche dans le textarea
    if(null !== $my_word_war) {
      $saved_words = html_entity_decode($my_word_war->getContent());
      $word_count = $my_word_war->getWordCount();
    }
    else {
      $my_word_war = new DailyWords($user, 'word_war', $word_war);
      $manager->persist($my_word_war);
      $manager->flush();
    }

    $date_start = $word_war->getStart();
    $date_end = $word_war->getEnd();
    $start_time = $date_start->format('Y-m-d H:i');
    $end_time = $date_end->format('Y-m-d H:i');

    return $this->render(
      'WordWarsBundle:WordWars:in_progress.html.twig',
      array(
        'saved_words' => $saved_words,
        'word_count' => $word_count,
        'start_time' => $start_time,
        'end_time' => $end_time,
        'ww_id' => $word_war->getId(),
        )
      );
  }

  public function saveAction(Request $request)
  {
    // On vérifie qu'il s'agit d'une requête AJAX
    // (normalement le router vérifie que c'est bien une requête POST)
    if($request->isXmlHttpRequest()) {
      // Récupération des mots et du word_count
      $ww_id = $request->request->get('ww_id');
      $post = $request->request->get('content');
      $word_count = $request->request->get('word_count');

      // Récupération du user
      $user = $this->getUser();
      $manager = $this
        ->getDoctrine()
        ->getManager();

      // Récupération des repos word_war, daily_words et stats
      $ww_repo = $manager->getRepository('WordWarsBundle:WordWar');
      $words_repo = $manager->getRepository('MyWordsBundle:DailyWords');
      $daily_stats_repo = $manager->getRepository('MyStatsBundle:MyDailyStats');

      // Récupération des
      $word_war = $ww_repo->find($ww_id);
      $ww_words = $words_repo->findByWWId($ww_id, $user);
      $stats = $daily_stats_repo->findTodaysStats($user);

      // Si ce sont les premiers mots de la journée, on crée la ligne du jour
      if(null === $ww_words) {
        $ww_words = new DailyWords($user, 'word_war', $word_war);
        $manager->persist($ww_words);
      }

      if(null === $stats) {
        $stats = new MyDailyStats($user);
        $manager->persist($stats);
      }

      // Mise à jour des mots du jour
      $ww_words->setContent(htmlentities($post));
      $ww_words->setWordCount($word_count);

      // Mise à jour des stats relatives aux mots du jour
      $ww_word_count = 0;

      $all_ww_words = $words_repo->findAllWWCount($user);

      foreach ($all_ww_words as $ww) {
        $ww_word_count+= $ww->getWordCount();
      }

      $stats->setDate(new \Datetime());
      $stats->setDaysGoal($user->getUserPreferences()->getWordCountGoal());
      $stats->setWordWarsWordCount($ww_word_count);

      // Sauvegarde en base
      $manager->flush();

      $days_progress = $this->forward('MyStatsBundle:DaysStats:daysProgress');

      $response = new JsonResponse(array(
        'status' => 'ok',
        'message' => 'progression sauvegardée',
        'days_progress' => $days_progress->getContent()));
    }
    else {
      $response = new JsonResponse(array(
        'status' => 'ko',
        'message' => 'problème lors de la sauvegarde'));
    }

    return $response;
  }

  public function wwEndedAction(Request $request, $id) {
    $user = $this->getUser();
    $manager = $this->getDoctrine()->getManager();

    $all_word_wars = null;

    // Récupération de la WW
    $ww_repo = $manager->getRepository('WordWarsBundle:WordWar');
    $word_war = $ww_repo->find($id);

    if(null !== $word_war) {
      $mww_repo = $manager->getRepository('MyWordsBundle:DailyWords');
      $all_results = $mww_repo->findWWParticipants($word_war);

      if(null !== $all_results) {
        $all_word_wars = array();

        foreach ($all_results as $result) {
          $avatar = $result->getUser()->getAvatar();

          $all_word_wars[] = array(
            'username' => $result->getUser()->getUsername(),
            'avatar' => (null !== $avatar) ? $avatar->getAvatarPath() : null,
            'word_count' => $result->getWordCount(),
            'is_me' => ($result->getUser()->getId() == $user->getId())
          );
        }
      }
    }

    return $this->render('WordWarsBundle:WordWars:ww_ended.html.twig', array(
      'all_ww' => $all_word_wars
    ));
  }
}
