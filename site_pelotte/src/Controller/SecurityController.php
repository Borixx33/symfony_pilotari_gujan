<?php

namespace App\Controller;

use App\Entity\Licence;
use App\Entity\Profil;
use App\Form\InscriptionType;

use Symfony\Component\HttpFoundation\Request;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;


class SecurityController extends AbstractController
{
    /**
     * @var \Swift_Mailer
     */
    private $mailer;

    //Recuperation par injection du Swift_Mailer
    public function __construct(\Swift_Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    public function InscriptionMail(Profil $profil)
    {
        // New mail
        $message = (new \Swift_Message('Nouvelle demande de connexion sur le site du pilotari : '))
            ->setFrom('noreply@pilotari_gujan.fr')
            ->setTo('tototata33380@gmail.com')
            ->setBody($this->renderView('site/mail_profil.html.twig', [
                'profil' => $profil
            ]), 'text/html');
        // Sent email
        $this->mailer->send($message);
    }

    /**
     * @Route("/inscription", name="inscription")
     */
    public function inscription(Request $request, ObjectManager $manager, UserPasswordEncoderInterface $encoder)
    {
        $profil = new Profil();
        $form = $this->createForm(InscriptionType::class, $profil);
        $session = $this->get('session');

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){
            $profilRepo = $this->getDoctrine()->getRepository(Profil::class);
            $licenceRepo = $this->getDoctrine()->getRepository(Licence::class);
            $isAlreadyRegistered = $profilRepo->findBy(['mail' => $form->getData()->getMail()]);

            if (empty($isAlreadyRegistered)) {
                $licence = $licenceRepo->findOneBy(['numLicence' => $form['numLicence']->getData()]);
                if (!empty($licence)) {
                    $hash = $encoder->encodePassword($profil, $profil->getPassword());

                    $profil->setPassword($hash);
                    $profil->setLicence($licence);

                    $this->InscriptionMail($profil);

                    $manager->persist($profil);
                    $manager->flush();
                    $session->remove('inscriptionData');
                } else {
                    $data = [
                        'username' => $form->getData()->getUsername(),
                        'lastname' => $form->getData()->getLastname(),
                        'mail' => $form->getData()->getMail()
                    ];

                    $session->set('inscriptionData', $data);

                    $this->addFlash(
                        'alert',
                        'Votre numéro de licence n\'est pas valide'
                    );

                    return $this->redirectToRoute('inscription');
                }
            }

            return $this->redirectToRoute('connexion');
        }

        return $this->render('site/inscription.html.twig',[
            'form' => $form->createView()
        ]);
    }
    /**
     * @Route("/login", name="connexion")
     */
    public function login(AuthenticationUtils $authenticationUtils)
    {
        $error = $authenticationUtils->getLastAuthenticationError();

        return $this->render('site/login.html.twig',[
            'error' => $error
        ]);
    }

    /**
     * @Route("/deconnexion", name="logout")
     */
    public function logout() {}
}
