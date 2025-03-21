# Table of Contents

1. [Proposal](#proposal)
   1.1 [Scheduler and Reactor](#scheduler-and-reactor)  
   1.2 [Limitations](#limitations)  
   1.3 [Namespace](#namespace)

2. [Coroutine](#coroutine)
   2.1 [Spawn](#spawn)  
   2.2 [Spawn Closure Syntax](#spawn-closure-syntax)  
   2.3 [In Scope Expression](#in-scope-expression)

3. [Suspension](#suspension)

4. [Input/Output Operations And Implicit Suspension](#inputoutput-operations-and-implicit-suspension)

5. [Await](#await)

6. [Edge Behavior](#edge-behavior)

7. [Awaitable Interface](#awaitable-interface)

8. [Scope and Structured Concurrency](#scope-and-structured-concurrency)  
   8.1 [Coroutine Scope Waiting](#coroutine-scope-waiting)  
   8.2 [Scope Cancellation](#scope-cancellation)  
   8.3 [Scope Hierarchy](#scope-hierarchy)

9. [Context](#context)  
   9.1 [Motivation](#motivation)  
   9.2 [Context API](#context-api)  
   9.3 [Context Inheritance](#context-inheritance)  
   9.4 [Coroutine Local Context](#coroutine-local-context)

10. [Error Handling](#error-handling)  
    10.1 [Responsibility Points](#responsibility-points)  
    10.2 [Exception Handling](#exception-handling)  
    10.3 [onCompletion](#oncompletion)

11. [Cancellation](#cancellation)  
    11.1 [CancellationException Propagation](#cancellationexception-propagation)  
    11.2 [exit and die Keywords](#exit-and-die-keywords)

12. [Graceful Shutdown](#graceful-shutdown)

13. [Deadlocks](#deadlocks)

14. [Tools](#tools)

15. [Prototypes](#prototypes)

16. [Backward Incompatible Changes](#backward-incompatible-changes)

17. [Proposed PHP Version(s)](#proposed-php-versions)