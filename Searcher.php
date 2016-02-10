<?php
namespace AppBundle\Service;

use FOS\ElasticaBundle\Finder\FinderInterface;
/**
 * Date: 2/10/16
 * Time: 12:39 PM
 */

/**
 * Class Searcher
 */
class Searcher
{
    /**
     * @var FinderInterface
     */
    protected $userFinder;

    /**
     * @var FinderInterface
     */
    protected $followFinder;

    public function __construct(FinderInterface $userFinder, FinderInterface $followFinder)
    {
        $this->userFinder = $userFinder;
        $this->followFinder = $followFinder;
    }

    /**
     * @param UserInterface $user
     * @param int $limit
     * @return mixed
     * @throws EmptyArgumentsException
     */
    public function findUsersWithStrategyLikeUser(UserInterface $user, $limit = 5)
    {
        $approach = $user->getApproach();
        $period = $user->getPeriod();
        $kind = $user->getKind();
        $assets = $user->getAssets();
        $experience = $user->getExperience();

        $boolQuery = new \Elastica\Query\Bool();

        //exact search
        $addQuery = new \Elastica\Query\Term();
        $addQuery->setTerm('approach', $approach);
        $boolQuery->addShould($addQuery);

        //exact search
        $addQuery = new \Elastica\Query\Term();
        $addQuery->setTerm('kind', $kind);
        $boolQuery->addShould($addQuery);

        //exact search + range search
        $addQuery = new \Elastica\Query\Term();
        $addQuery->setTerm('experience', $experience, 0.7);
        $boolQuery->addShould($addQuery);

        $addQuery = new \Elastica\Query\Range(
            'experience', [
                "boost" => 0.3,
                "gte"   => $experience - 1,
                "lte"   => $experience + 1
            ]
        );
        $boolQuery->addShould($addQuery);

        //exact search + range search
        $addQuery = new \Elastica\Query\Term();
        $addQuery->setTerm('period', $period, 0.7);
        $boolQuery->addShould($addQuery);

        $addQuery = new \Elastica\Query\Range(
            'period', [
                "boost" => 0.3,
                "gte"   => $period - 1,
                "lte"   => $period + 1
            ]
        );
        $boolQuery->addShould($addQuery);

        //exclude given user + already followed users
        $filter = new \Elastica\Filter\Term();
        $filter->setTerm('idFollower', $user->getId());
        $subquery = new \Elastica\Query\Filtered(new \Elastica\Query\MatchAll(), $filter);
        $query = new \Elastica\Query();
        $query->setQuery($subquery);
        //todo: that only supports up to 1000 followers, change if needed
        $results = $this->followFinder->find($query, 1000);
        $ids = [$user->getId()];
        foreach($results as $result) {
            $ids[] = $result->getFollowee()->getId();
        }

        $addQuery = new \Elastica\Query\Terms('id', $ids);
        $boolQuery->addMustNot($addQuery);
        //

        if(!is_null($assets)) {
            //match half of array size, boost lowered cause this kind of search(array to array) is less reliable
            $addQuery = new \Elastica\Query\Terms('assets', $assets);
            $addQuery->setParam("boost", 0.8);
            $addQuery->setMinimumMatch(count($assets) / 2);
            $boolQuery->addShould($addQuery);
        }

        if(!is_null($user->getCountry())) {
            //boost raised to 2
            $addQuery = new \Elastica\Query\Term();
            $addQuery->setTerm('country', $user->getCountry(), 2);
            $boolQuery->addShould($addQuery);
        }

        return $this->userFinder->find($boolQuery, $limit);
    }
}
